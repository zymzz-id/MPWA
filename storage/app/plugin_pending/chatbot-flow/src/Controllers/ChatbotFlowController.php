<?php

namespace Plugins\ChatbotFlow\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChatbotFlow;
use Illuminate\Http\Request;

class ChatbotFlowController extends Controller
{
    public function index(Request $request)
    {
        $deviceId = session('selectedDevice')['device_id'] ?? null;
        $flows = ChatbotFlow::where('user_id', auth()->id())
            ->where('device_id', $deviceId)
            ->when($request->keyword, fn($q, $k) => $q->where('keyword', 'like', "%$k%"))
            ->latest()
            ->paginate(12);

        return view('chatbot-flow::index', compact('flows'));
    }

    public function create()
    {
        return view('chatbot-flow::editor', ['flow' => null]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'keyword' => 'required',
            'type_keyword' => 'required',
            'reply_when' => 'required',
        ]);

        ChatbotFlow::create([
            'user_id' => auth()->id(),
            'device_id' => session('selectedDevice')['device_id'] ?? null,
            'name' => $request->name,
            'keyword' => $request->keyword,
            'type_keyword' => $request->type_keyword,
            'reply_when' => $request->reply_when,
            'status' => 'active',
        ]);

        return redirect()->route('chatbot-flow')->with('alert', ['type' => 'success', 'msg' => __('Flow created successfully.')]);
    }

    public function edit($id)
    {
        $flow = ChatbotFlow::where('user_id', auth()->id())->findOrFail($id);

        return view('chatbot-flow::editor', compact('flow'));
    }

    public function update(Request $request, $id)
    {
        $flow = ChatbotFlow::where('user_id', auth()->id())->findOrFail($id);
        $flow->update([
            'name' => $request->name,
            'keyword' => $request->keyword,
            'type_keyword' => $request->type_keyword,
            'reply_when' => $request->reply_when,
            'flow_data' => json_decode($request->flow_data),
        ]);

        return response()->json(['error' => false]);
    }

    public function save(Request $request, $id)
    {
        $flow = ChatbotFlow::where('user_id', auth()->id())->findOrFail($id);
        $flow->update([
            'flow_data' => $request->flow_data,
        ]);

        return response()->json(['error' => false]);
    }

    public function destroy(Request $request)
    {
        $flow = ChatbotFlow::where('user_id', auth()->id())->findOrFail($request->id);
        $flow->delete();

        return response()->json(['error' => false, 'message' => __('Flow deleted')]);
    }

    public function status(Request $request, $id)
    {
        $flow = ChatbotFlow::where('user_id', auth()->id())->findOrFail($id);
        $flow->update(['status' => $request->status]);

        return response()->json(['error' => false, 'message' => __('Status updated')]);
    }
}
