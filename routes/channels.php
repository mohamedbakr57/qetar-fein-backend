<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Train\Train;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channels - no authentication required
Broadcast::channel('trains.live.updates', function () {
    return true; // Public channel
});

Broadcast::channel('trains.{trainId}.location', function ($user, $trainId) {
    return true; // Public train tracking
});

// Private user channels
Broadcast::channel('users.{userId}.notifications', function ($user, $userId) {
    return $user && $user->id == $userId;
});

// Community channels
Broadcast::channel('communities.{communityId}.messages', function ($user, $communityId) {
    // Guest users can listen (view), authenticated users can participate
    return true;
});

// Admin channels
Broadcast::channel('admin.train.monitoring', function ($user) {
    return $user && ($user->hasRole('admin') ?? false);
});
