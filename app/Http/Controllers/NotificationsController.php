<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    public function markAsRead($id = null)
    {
        // dd($id);
        $user = Auth::user();
        try {
            if ($id) {
                $notification = $user->notifications()->find($id);
                if ($notification && $notification->read_at == null) {
                    $notification->markAsRead();
                    return response()->json(['success' => true]);
                }
            } else {

                $user->unreadNotifications->markAsRead();

                return response()->json(['success' => true]);
            }
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()]);
        }
    }


    public function destroy($id = null)
    {
        // dd($id);
        $user = Auth::user();
        try {
            if ($id) {
                $notification = $user->notifications()->find($id);
                if ($notification) {
                    $notification->delete();
                    return response()->json(['success' => true]);
                }
            } else {
                $user->notifications()->delete();
                return response()->json(['success' => true]);
            }
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()]);
        }
    }
}