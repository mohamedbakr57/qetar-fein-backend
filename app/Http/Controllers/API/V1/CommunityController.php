<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMessage;
use App\Models\Train\TrainTrip;
use App\Models\Train\Station;
use App\Events\CommunityMessagePosted;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CommunityController extends Controller
{
    public function show(int $tripId, Request $request): JsonResponse
    {
        $trip = TrainTrip::with('train')->find($tripId);

        if (!$trip) {
            return response()->json([
                'status' => 'error',
                'message' => 'Trip not found'
            ], 404);
        }

        // Get train name - handle both JSON and plain text formats
        $trainNameAr = is_array($trip->train->name) ? $trip->train->name['ar'] : $trip->train->name;
        $trainNameEn = is_array($trip->train->name) ? $trip->train->name['en'] : $trip->train->name;

        // Find or create community for this trip
        $community = Community::firstOrCreate(
            ['trip_id' => $tripId],
            [
                'name' => [
                    'ar' => 'مجتمع رحلة ' . $trainNameAr,
                    'en' => $trainNameEn . ' Trip Community'
                ],
                'status' => 'active'
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'community' => $community,
                'trip' => $trip,
                'member_count' => $community->member_count,
                'message_count' => $community->message_count,
            ]
        ]);
    }

    public function messages(int $tripId, Request $request): JsonResponse
    {
        $community = Community::where('trip_id', $tripId)->first();

        if (!$community) {
            return response()->json([
                'status' => 'error',
                'message' => 'Community not found'
            ], 404);
        }

        $query = CommunityMessage::with(['user', 'station'])
            ->where('community_id', $community->id);

        // Filter by message type
        if ($request->has('type')) {
            $query->where('message_type', $request->type);
        }

        // Filter by station
        if ($request->has('station_id')) {
            $query->where('station_id', $request->station_id);
        }

        // Filter by verified messages only
        if ($request->boolean('verified_only')) {
            $query->where('is_verified', true);
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'status' => 'success',
            'data' => [
                'community' => $community,
                'messages' => $messages->items(),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ]
            ]
        ]);
    }

    public function postMessage(int $tripId, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'station_id' => 'required|exists:stations,id',
            'time_passed_minutes' => 'required|integer|min:0|max:1440', // Max 24 hours
            'message_type' => 'required|in:status_update,delay_report,arrival_confirmation,departure_confirmation,crowd_level,amenity_status',
            'additional_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        $community = Community::where('trip_id', $tripId)->first();

        if (!$community) {
            return response()->json([
                'status' => 'error',
                'message' => 'Community not found'
            ], 404);
        }

        $user = $request->user();
        $guestId = null;

        // Handle guest users
        if (!$user) {
            if (!$request->has('guest_name')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Guest name is required for anonymous messages'
                ], 422);
            }

            $guestId = Str::random(10);
        }

        // Verify station is on the train's route (from stops)
        $trip = TrainTrip::with('train.stops')->find($tripId);
        $trainStationIds = $trip->train->stops->pluck('station_id');

        if (!$trainStationIds->contains($request->station_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Selected station is not on this train route'
            ], 400);
        }

        // Rate limiting: max 5 messages per user per trip
        $existingMessagesCount = CommunityMessage::where('community_id', $community->id)
            ->where(function($query) use ($user, $guestId) {
                if ($user) {
                    $query->where('user_id', $user->id);
                } else {
                    $query->where('guest_id', $guestId);
                }
            })
            ->count();

        if ($existingMessagesCount >= 5) {
            return response()->json([
                'status' => 'error',
                'message' => 'Maximum message limit reached for this trip'
            ], 429);
        }

        $message = CommunityMessage::create([
            'community_id' => $community->id,
            'user_id' => $user?->id,
            'guest_id' => $guestId,
            'guest_name' => $request->guest_name,
            'station_id' => $request->station_id,
            'time_passed_minutes' => $request->time_passed_minutes,
            'message_type' => $request->message_type,
            'additional_data' => $request->additional_data ?? [],
            'is_verified' => false,
            'verification_count' => 0,
        ]);

        // Update community stats
        $community->increment('message_count');

        // Load relationships
        $message->load(['user', 'station']);

        // Broadcast message to community
        broadcast(new CommunityMessagePosted($message))->toOthers();

        return response()->json([
            'status' => 'success',
            'message' => 'Message posted successfully',
            'data' => [
                'message' => $message
            ]
        ], 201);
    }

    public function verifyMessage(int $tripId, int $messageId, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verification_type' => 'required|in:confirm,dispute',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verification type',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required for message verification'
            ], 401);
        }

        $community = Community::where('trip_id', $tripId)->first();

        if (!$community) {
            return response()->json([
                'status' => 'error',
                'message' => 'Community not found'
            ], 404);
        }

        $message = CommunityMessage::where('id', $messageId)
            ->where('community_id', $community->id)
            ->first();

        if (!$message) {
            return response()->json([
                'status' => 'error',
                'message' => 'Message not found'
            ], 404);
        }

        // Check if user already verified this message
        if ($message->verifications()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have already verified this message'
            ], 400);
        }

        // Can't verify own message
        if ($message->user_id === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot verify your own message'
            ], 400);
        }

        // Create verification
        $message->verifications()->create([
            'user_id' => $user->id,
            'verification_type' => $request->verification_type,
        ]);

        // Update verification count and status
        $confirmations = $message->verifications()->where('verification_type', 'confirm')->count();
        $disputes = $message->verifications()->where('verification_type', 'dispute')->count();

        $message->update([
            'verification_count' => $confirmations + $disputes,
            'is_verified' => $confirmations >= 3 && $confirmations > $disputes,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Message verification recorded',
            'data' => [
                'message_id' => $message->id,
                'verification_count' => $message->verification_count,
                'is_verified' => $message->is_verified,
                'confirmations' => $confirmations,
                'disputes' => $disputes,
            ]
        ]);
    }

    public function getMessageTypes(): JsonResponse
    {
        $messageTypes = [
            'status_update' => [
                'name' => ['ar' => 'تحديث الحالة', 'en' => 'Status Update'],
                'icon' => 'info-circle',
                'description' => ['ar' => 'تحديث عام حول حالة القطار', 'en' => 'General train status update']
            ],
            'delay_report' => [
                'name' => ['ar' => 'تقرير تأخير', 'en' => 'Delay Report'],
                'icon' => 'clock',
                'description' => ['ar' => 'تقرير عن تأخير القطار', 'en' => 'Report train delays']
            ],
            'arrival_confirmation' => [
                'name' => ['ar' => 'تأكيد الوصول', 'en' => 'Arrival Confirmation'],
                'icon' => 'map-pin',
                'description' => ['ar' => 'تأكيد وصول القطار للمحطة', 'en' => 'Confirm train arrival at station']
            ],
            'departure_confirmation' => [
                'name' => ['ar' => 'تأكيد المغادرة', 'en' => 'Departure Confirmation'],
                'icon' => 'arrow-right',
                'description' => ['ar' => 'تأكيد مغادرة القطار من المحطة', 'en' => 'Confirm train departure from station']
            ],
            'crowd_level' => [
                'name' => ['ar' => 'مستوى الازدحام', 'en' => 'Crowd Level'],
                'icon' => 'users',
                'description' => ['ar' => 'تقرير عن مستوى الازدحام في القطار', 'en' => 'Report train crowding level']
            ],
            'amenity_status' => [
                'name' => ['ar' => 'حالة المرافق', 'en' => 'Amenity Status'],
                'icon' => 'settings',
                'description' => ['ar' => 'تقرير عن حالة مرافق القطار', 'en' => 'Report train amenities status']
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'message_types' => $messageTypes
            ]
        ]);
    }
}