<?php
/*
Copyright © Magd Almuntaser, OneXGen Technology. All rights reserved.
Project: MPWA Whatsapp Gateway | Multi Device
Licensed under the CC BY-NC-ND 4.0 License.
For details, visit https://creativecommons.org/licenses/by-nc-nd/4.0/.
*/

namespace App\Services\Impl;

use App\Services\MessageService;

class MessageServiceImpl implements MessageService
{
    public function formatText($text, $footer = ''): array
    {
        return [
				'text' => $text,
				'footer' => $footer,
			];
    }
	
	public function formatChannel($text, $footer = ''): array
    {
        return [
				'text' => $text,
				'footer' => $footer,
			];
    }
	
	public function formatLocation($latitude, $longitude): array
	{
		return [
			'location' => [
				'degreesLatitude' => $latitude,
				'degreesLongitude' => $longitude,
			],
		];
	}
	
	public function formatProduct($data): array
	{
		$product = [
			'productImage' => [
				'url' => $data->image ?? 'https://placehold.co/600x400?text=No+Image'
			],
			'productId' => $data->product_id,
			'productImageCount' => 1,
			'title' => $data->product_title ?? '',
			'description' => $data->description ?? '',
			'currencyCode' => $data->currency ?? '',
			'retailerId' => $data->company_name ?? '',
			'url' => '',
			'signedUrl' => '',
		];

		if (!empty($data->price) && !empty($data->old_price)) {
			$product['priceAmount1000'] = preg_replace('/[^\d]/', '', $data->old_price) * 1000;
			$product['salePriceAmount1000'] = preg_replace('/[^\d]/', '', $data->price) * 1000;
		} elseif (!empty($data->price)) {
			$product['priceAmount1000'] = preg_replace('/[^\d]/', '', $data->price) * 1000;
		}

		return [
			'product' => $product,
			'businessOwnerJid' => ($data->phone ?? '') . '@s.whatsapp.net',
			'caption' => $data->description ?? '',
			'title' => $data->product_title ?? '',
			'footer' => $data->footer ?? '',
			'media' => true
		];
	}
	
	public function formatVcard($name, $phone): array
	{
		$vcard = 
			"BEGIN:VCARD\n" . 
			"VERSION:3.0\n" . 
			"FN:" . $name . "\n" . 
			"TEL;type=CELL;type=VOICE;waid=" . $phone . ":+" . $phone . "\n" . 
			"END:VCARD";

		return [
			'contacts' => [
				'displayName' => $name,
				'contacts' => [['vcard' => $vcard]]
			]
		];
	}

    public function formatImage($url, $caption = ''): array
    {
        return ['image' => ['url' => $url], 'caption' => $caption];
    }

    // formating buttons
    public function formatButtons($text, $buttons, $urlimage = '', $footer = ''): array
    {
        $optionbuttons = [];
        $i = 1;
        foreach ($buttons as $button) {
            $optionbuttons[] = [
                'buttonId' => "id$i",
                'buttonText' => ['displayText' => $button],
                'type' => 1,
            ];
            $i++;
        }
        $valueForText = $urlimage ? 'caption' : 'text';
        $message = [
            $valueForText => $text,
            'buttons' => $optionbuttons,
            'footer' => $footer,
            'headerType' => 1,
            'viewOnce' => true,
        ];
        if ($urlimage) {
            $message['image'] = ['url' => $urlimage];
        }
        return $message;
    }

    public function formatLists($text, $name, $sections, $buttonText, $footer = '', $image = ''): array
	{
		$formattedSections = [];

		foreach ($sections as $section) {
			$formattedRows = [];

			if (isset($section['rows'])) {
				foreach ($section['rows'] as $row) {
					$formattedRows[] = [
						'title' => $row['title'],
						'rowId' => uniqid('id'),
						'description' => $row['description'] ?? '',
					];
				}
			}

			$formattedSections[] = [
				'title' => $section['title'],
				'rows' => $formattedRows,
			];
		}

		$listMessage = [
			'text' => $text,
			'footer' => $footer,
			'title' => $name,
			'buttonText' => $buttonText,
			'sections' => $formattedSections,
		];

		if ($image) {
			$listMessage['image'] = ['url' => $image];
		}

		return $listMessage;
	}



    public function format($type, $data): array
    {
        switch ($type) {
            case 'text':
                $reply = $this->formatText($data->message, $data->footer);
                break;
			case 'channel':
                $reply = $this->formatChannel($data->message, $data->footer);
                break;
			case 'location':
                $reply = $this->formatLocation($data->latitude, $data->longitude);
                break;
			case 'product':
                $reply = $this->formatProduct($data);
                break;
			case 'vcard':
                $reply = $this->formatVcard($data->name, $data->phone);
                break;
            case 'image':
                $reply = $this->formatImage($data->image,  $data->caption);
                break;
            case 'button':
                $buttons = [];
                foreach ($data->button as $button) {
                    $buttons[] = $button;
                }
                $reply = $this->formatButtons($data->message, $buttons, $data->image ? $data->image : '', $data->footer ?? '');
                break;
            case 'list':
                if (!isset($data->sections)) {
					throw new \Exception('Sections data is missing');
				}
				$reply = $this->formatLists($data->message, $data->name, $data->sections, $data->buttontext, $data->footer ?? '', $data->image ?? '');
				break;
			case 'sticker':
                $reply = $this->formatSticker($data);
                break;
            case 'media':
                $reply = $this->formatMedia($data);
                break;
            default:
                # code...
                break;
        }

        return $reply;
    }

	private function formatSticker($data)
    {
        //Log::info('data' . json_encode($data));
        $fileName = explode('/', $data->url);
        $fileName = explode('.', end($fileName));
        $fileName = implode('.', $fileName);
        $mediadetail = [
            'type' => 'sticker',
            'url' => $data->url,
            'filename' => $fileName,
        ];

        return $mediadetail;
    }

    private function formatMedia($data)
    {
        //Log::info('data' . json_encode($data));
        $fileName = explode('/', $data->url);
        $fileName = explode('.', end($fileName));
        $fileName = implode('.', $fileName);
        $mediadetail = [
            'type' => $data->media_type,
            'url' => $data->url,
            'caption' => $data->caption ?? '',
			'footer' => $data->footer ?? '',
			'viewonce' => $data->viewonce ?? '',
            'filename' => $fileName,
        ];

        return $mediadetail;
    }
}
?>