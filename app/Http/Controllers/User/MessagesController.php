<?php
/*
Copyright © Magd Almuntaser, OneXGen Technology. All rights reserved.
Project: MPWA Whatsapp Gateway | Multi Device
Licensed under the CC BY-NC-ND 4.0 License.
For details, visit https://creativecommons.org/licenses/by-nc-nd/4.0/.
*/

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\MessageHistory;
use App\Models\Device;
use App\Repositories\DeviceRepository;
use App\Services\WhatsappService;
use App\Utils\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MessagesController extends Controller
{
    protected WhatsappService $whatsappService;
    protected DeviceRepository $deviceRepository;
    protected $processor = [
        'text' => 'sendText',
        'media' => 'sendMedia',
		'sticker' => 'sendSticker',
        'button' => 'sendButton',
        'product' => 'sendProduct',
		'textchannel' => 'sendTextChannel',
        'list' => 'sendList',
        'poll' => 'sendPoll',
		'location' => 'sendLocation',
		'vcard' => 'sendVcard',
    ];

    public function __construct(WhatsappService $whatsappService, DeviceRepository $deviceRepository)
    {
        $this->whatsappService = $whatsappService;
        $this->deviceRepository = $deviceRepository;
    }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $devices = $request->user()->devices()->latest()->paginate(10);
        return view('theme::pages.messagetest', compact('devices'));
    }

    /**
     * Sending and storing message successfully
     */
    public function store(SendMessageRequest $request)
	{
		$receivers = explode('|', $request->number);
		$unique = array_unique($receivers);

		$user = $request->user();
		$planData = $user->plan_data;

		if (isset($planData['messages_limit']) && $planData['messages_limit'] > 0) {
			$messagesLimit = $planData['messages_limit'];
			if (count($unique) > $messagesLimit) {
				$unique = array_slice($unique, 0, $messagesLimit);
			}
		}

		$type = $request->type;

		$success = 0;
		$dataForBatchInput = [];
		//$device = Cache::remember(CacheKey::DEVICE_BY_BODY . $request->sender, 60 * 60 * 12, fn () => $this->deviceRepository->byBody($request->sender)->single());
		$device = $device = $this->deviceRepository->byBody($request->sender)->single();

		foreach ($unique as $number) {
			try {
				$method = $this->processor[$type];
				$messageSent = $this->whatsappService->$method($request, $number);

				$dataForBatchInput[] = [
					'user_id' => $request->user()->id,
					'device_id' => $device->id,
					'number' => $number,
					'message' => $request->message ? $request->message : ($request->caption ? $request->caption : ''),
					'payload' => json_encode($request->all()),
					'status' => $messageSent->status ? 'success' : 'failed',
					'type' => $request->type,
					'send_by' => 'web',
					'created_at' => now(),
					'updated_at' => now(),
				];

				$success = $messageSent->status ? $success + 1 : $success;
			} catch (\Exception $e) {
				return backWithFlash('danger', __('Failed to send message to all numbers, check your WhatsApp connection and try again.'));
				Log::error('Error sending message to ' . $number . ': ' . $e->getMessage());
			}
		}

		if (isset($planData['messages_limit'])) {
			$planData['messages_limit'] = max(0, $planData['messages_limit'] - $success);
			$user->plan_data = $planData;
			$user->save();
		}

		MessageHistory::insert($dataForBatchInput);
		$this->deviceRepository->incrementMessageSent($device->id, $success);

		return backWithFlash(
			$success > 0 ? 'success' : 'danger',
			$success > 0 ? __('Message sent to :success number', ['success' => $success]) : __("Failed to send message to all numbers, check your WhatsApp connection and try again.")
		);
	}
	
	public function fetchChannel(Request $request)
	{
		$url = $request->url;
		if (!$url || !str_contains($url, 'whatsapp.com/channel/')) {
			return response()->json(['error' => 'Invalid URL'], 400);
		}
		$urlRemoved = preg_replace('/^(https?:\/\/)?(www\.)?whatsapp\.com\/channel\//i', '', $url);
		$number = session('selectedDevice')['device_id'];
		$device = Device::find($number);
		return $this->whatsappService->fetchChannel($device, $urlRemoved);
	}
	
	public function fetchWhatsAppProduct(Request $request)
	{
		$url = $request->query('url');
		if (! $url || ! str_contains($url, 'wa.me/p/')) {
			return response()->json(['error' => 'Invalid URL'], 400);
		}

		$response = Http::timeout(10)->get($url);
		$data = [
			'productTitle' => '',
			'companyName'  => '',
			'description'  => '',
			'price'        => '',
			'oldPrice'     => '',
			'currency'     => '',
			'image'        => '',
			'productId'    => '',
			'phoneNumber'  => '',
		];

		if ($response->successful()) {
			$html = $response->body();

			preg_match('/<title.*?>(.*?)<\/title>/si', $html, $titleMatch);
			$fullTitle = trim(html_entity_decode($titleMatch[1] ?? '', ENT_QUOTES | ENT_HTML5));
			if (preg_match('/^(.*?)\s+from\s+(.*?)\s+on WhatsApp\.?$/i', $fullTitle, $matches)) {
				$data['productTitle'] = trim($matches[1]);
				$data['companyName']  = trim($matches[2]);
			}

			preg_match('/<meta name="description" content="([^"]+)"/si', $html, $descMatch);
			$descriptionRaw = trim(html_entity_decode($descMatch[1] ?? '', ENT_QUOTES | ENT_HTML5));
			if (preg_match('/·\s*(IDR)\s?([\d.,]+)/ui', $descriptionRaw, $currentPriceMatch)) {
				$data['currency'] = trim($currentPriceMatch[1]);
				$data['price']    = trim($currentPriceMatch[2]);
				$descriptionRaw   = preg_replace('/·\s*IDR\s?[\d.,]+/ui', '', $descriptionRaw);
			}
			if (preg_match('/\(was\s+(IDR)\s?([\d.,]+)\)/ui', $descriptionRaw, $oldPriceMatch)) {
				$data['oldPrice'] = trim($oldPriceMatch[2]);
				$descriptionRaw   = preg_replace('/\(was\s+IDR\s?[\d.,]+\)/ui', '', $descriptionRaw);
			}
			$data['description'] = trim($descriptionRaw);

			preg_match('#wa\.me/p/(\d+)/(\d+)#', $url, $linkMatch);
			$productId   = $linkMatch[1] ?? '';
			$phoneNumber = $linkMatch[2] ?? '';
			$data['productId']   = $productId;
			$data['phoneNumber'] = $phoneNumber;

			preg_match('/<meta property="og:image" content="([^"]+)"/si', $html, $imageMatch);
			$remote = html_entity_decode($imageMatch[1] ?? '', ENT_QUOTES | ENT_HTML5);
			if ($remote) {
				$imgResponse = Http::timeout(15)->get($remote);
				if ($imgResponse->successful()) {
					$contents = $imgResponse->body();
					$ext = pathinfo(parse_url($remote, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
					$file = "whatsapp_products/{$productId}-{$phoneNumber}.{$ext}";
					Storage::disk('public')->put($file, $contents);
					$data['image'] = url(Storage::url($file));
				}
			}
		}

		return response()->json($data);
	}
}
?>