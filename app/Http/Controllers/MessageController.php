<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::of(Message::orderByDesc('id'))
                ->addColumn('checkbox', function ($message) {
                    return '<input type="checkbox" class="form-check-input checkbox" title="Select Record" value="'.$message->id.'">';
                })
                ->editColumn('full_name', function ($message) {
                    return ucwords($message->full_name);
                })
                ->editColumn('message', function ($message) {
                    $fullMessage = ucfirst($message->message);
                    $shortMessage = Str::limit($fullMessage, 80);

                    return '<span class="cursor-pointer"
                    title="'.e($fullMessage).'">'
                      .e($shortMessage).
                      '</span>';
                })
                ->addColumn('replied', function ($message) {
                    $badgeClass = $message->is_replied ? 'bg-label-success' : 'bg-label-secondary';
                    $replied = $message->is_replied ? 'Yes' : 'No';

                    return '<span class="badge '.$badgeClass.'">'.$replied.'</span>';
                })
                ->addColumn('actions', function ($message) {
                    $reply = '';
                    $delete = '<form action="'.route('message.destroy', $message->uuid).'" method="POST" class="me-2 d-inline">'
                      .csrf_field()
                      .method_field('DELETE')
                      .'<i class="fa-solid fa-trash-can text-danger" role="button" title="Delete" onclick="confirmDelete(event)"></i>'
                      .'</form>';

                    if (! $message->is_replied) {
                        $reply = '<i class="fa-solid fa-reply text-primary reply_on_contact "
                        role="button"
                        title="Reply"
                        data-email="'.$message->email.'">
                      </i>';
                    }

                    return $delete.' '.$reply;
                })
                ->rawColumns(['checkbox', 'replied', 'actions', 'message'])
                ->make(true);
        }
        $show_actions = true;

        return view('content.contact messages.index', compact('show_actions'));
    }

    public function destroy($uuid)
    {
        try {
            $message = Message::where('uuid', $uuid)->first();
            $message->delete();

            return back()->with('success', 'Message deleted successfully. ');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            Message::whereIn('id', $request->ids)->delete();

            return response()->json(['success' => true, 'message' => 'Selected messages deleted successfully.']);
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function reply(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:messages,id',
            'to_email' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $message = Message::findOrFail($request->message_id);

        try {
            Mail::raw($request->body, function ($mail) use ($request) {
                $mail->to($request->to_email)
                    ->subject($request->subject);
            });

            $message->update(['is_replied' => true]);

            return back()->with('success', 'Reply sent successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to send email: '.$e->getMessage());
        }
    }
}
