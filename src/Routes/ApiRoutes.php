<?php

declare(strict_types=1);

namespace AvatarTok\Routes;

use AvatarTok\Core\Router;
use AvatarTok\Middleware\AuthMiddleware;
use AvatarTok\Middleware\AdminMiddleware;
use AvatarTok\Middleware\ModerationMiddleware;
use AvatarTok\Middleware\ThrottleMiddleware;

// Controllers
use AvatarTok\Controllers\Auth\RegisterController;
use AvatarTok\Controllers\Auth\LoginController;
use AvatarTok\Controllers\Auth\RefreshTokenController;
use AvatarTok\Controllers\Users\UserController;
use AvatarTok\Controllers\Users\AvatarController;
use AvatarTok\Controllers\Videos\VideoController;
use AvatarTok\Controllers\Videos\FeedController;
use AvatarTok\Controllers\Videos\EffectsController;
use AvatarTok\Controllers\Live\LiveStreamController;
use AvatarTok\Controllers\Chat\ChatController;
use AvatarTok\Controllers\Sounds\SoundController;
use AvatarTok\Controllers\Social\FriendController;
use AvatarTok\Controllers\Social\NotificationController;
use AvatarTok\Controllers\Monetization\GiftController;
use AvatarTok\Controllers\Monetization\AdController;
use AvatarTok\Controllers\Monetization\PaymentController;
use AvatarTok\Controllers\Moderation\ModerationController;
use AvatarTok\Controllers\Analytics\AnalyticsController;
use AvatarTok\Controllers\Admin\AdminController;

class ApiRoutes
{
    public static function register(Router $router): void
    {
        // ── Root / Health ─────────────────────────────────────────────────────
        $router->get('/', [AdminController::class, 'root']);
        $router->get('/health', [AdminController::class, 'health']);

        // ── Authentication ────────────────────────────────────────────────────
        $router->group('/api/v1/auth', function ($g) {
            $g->post('/register',         [RegisterController::class,     'register'],  [ThrottleMiddleware::class]);
            $g->post('/login',            [LoginController::class,        'login'],     [ThrottleMiddleware::class]);
            $g->post('/refresh',          [RefreshTokenController::class, 'refresh']);
            $g->post('/logout',           [LoginController::class,        'logout'],    [AuthMiddleware::class]);
            $g->post('/forgot-password',  [LoginController::class,        'forgotPassword'], [ThrottleMiddleware::class]);
            $g->post('/reset-password',   [LoginController::class,        'resetPassword']);
            $g->post('/verify-email',     [RegisterController::class,     'verifyEmail']);
            $g->post('/verify-phone',     [RegisterController::class,     'verifyPhone']);
        });

        // ── Users ─────────────────────────────────────────────────────────────
        $router->group('/api/v1/users', function ($g) {
            $g->get('/me',                          [UserController::class, 'me']);
            $g->put('/me',                          [UserController::class, 'updateProfile']);
            $g->delete('/me',                       [UserController::class, 'deleteAccount']);
            $g->post('/me/change-password',         [UserController::class, 'changePassword']);
            $g->get('/{username}',                  [UserController::class, 'profile']);
            $g->get('/{username}/videos',           [VideoController::class, 'byUser']);
            $g->get('/{username}/liked',            [VideoController::class, 'likedByUser']);
            $g->get('/search',                      [UserController::class, 'search']);
            $g->get('/suggested',                   [UserController::class, 'suggested']);
        }, [AuthMiddleware::class]);

        // ── Avatars ───────────────────────────────────────────────────────────
        $router->group('/api/v1/avatars', function ($g) {
            $g->get('/me',                     [AvatarController::class, 'getMyAvatar']);
            $g->post('/me',                    [AvatarController::class, 'createOrUpdate']);
            $g->get('/presets',                [AvatarController::class, 'listPresets']);
            $g->get('/accessories',            [AvatarController::class, 'listAccessories']);
            $g->post('/face-calibrate',        [AvatarController::class, 'faceCalibrate']);
            $g->post('/face-tracking-session', [AvatarController::class, 'startFaceTrackingSession']);
            $g->get('/expressions',            [AvatarController::class, 'listExpressions']);
            $g->post('/unlock-accessory',      [AvatarController::class, 'unlockAccessory']);
        }, [AuthMiddleware::class]);

        // ── Videos ───────────────────────────────────────────────────────────
        $router->group('/api/v1/videos', function ($g) {
            $g->get('/feed',                [FeedController::class, 'algorithmicFeed']);
            $g->get('/trending',           [FeedController::class, 'trending']);
            $g->get('/following',          [FeedController::class, 'followingFeed']);
            $g->get('/search',             [VideoController::class, 'search']);
            $g->get('/{videoId}',          [VideoController::class, 'show']);
            $g->post('/',                  [VideoController::class, 'upload'],   [ModerationMiddleware::class]);
            $g->patch('/{videoId}',        [VideoController::class, 'update']);
            $g->delete('/{videoId}',       [VideoController::class, 'delete']);
            $g->post('/{videoId}/like',    [VideoController::class, 'like']);
            $g->delete('/{videoId}/like',  [VideoController::class, 'unlike']);
            $g->get('/{videoId}/comments', [VideoController::class, 'listComments']);
            $g->post('/{videoId}/comments',[VideoController::class, 'addComment'],  [ModerationMiddleware::class]);
            $g->delete('/comments/{commentId}', [VideoController::class, 'deleteComment']);
            $g->post('/{videoId}/share',   [VideoController::class, 'share']);
            $g->post('/{videoId}/report',  [VideoController::class, 'report']);
            $g->post('/{videoId}/duet',    [VideoController::class, 'duet'],     [ModerationMiddleware::class]);
            $g->post('/{videoId}/stitch',  [VideoController::class, 'stitch'],   [ModerationMiddleware::class]);
            $g->get('/upload/presigned',   [VideoController::class, 'presignedUploadUrl']);
            $g->post('/upload/complete',   [VideoController::class, 'completeUpload']);
            $g->get('/upload/status/{uploadId}', [VideoController::class, 'uploadStatus']);
        }, [AuthMiddleware::class]);

        // ── Video Effects & Filters ───────────────────────────────────────────
        $router->group('/api/v1/effects', function ($g) {
            $g->get('/',                  [EffectsController::class, 'list']);
            $g->get('/categories',        [EffectsController::class, 'categories']);
            $g->get('/trending',          [EffectsController::class, 'trending']);
            $g->get('/{effectId}',        [EffectsController::class, 'show']);
            $g->post('/{effectId}/apply', [EffectsController::class, 'apply']);
            $g->get('/avatar-filters',    [EffectsController::class, 'avatarFilters']);
            $g->post('/preview',          [EffectsController::class, 'preview']);
        }, [AuthMiddleware::class]);

        // ── Sound Library ─────────────────────────────────────────────────────
        $router->group('/api/v1/sounds', function ($g) {
            $g->get('/',                  [SoundController::class, 'list']);
            $g->get('/trending',          [SoundController::class, 'trending']);
            $g->get('/categories',        [SoundController::class, 'categories']);
            $g->get('/search',            [SoundController::class, 'search']);
            $g->get('/{soundId}',         [SoundController::class, 'show']);
            $g->get('/{soundId}/videos',  [SoundController::class, 'videosWithSound']);
            $g->post('/upload',           [SoundController::class, 'upload'], [ModerationMiddleware::class]);
            $g->post('/{soundId}/favorite',[SoundController::class, 'favorite']);
            $g->delete('/{soundId}/favorite',[SoundController::class, 'unfavorite']);
            $g->get('/my/favorites',      [SoundController::class, 'myFavorites']);
        }, [AuthMiddleware::class]);

        // ── Live Streaming ────────────────────────────────────────────────────
        $router->group('/api/v1/live', function ($g) {
            $g->post('/start',             [LiveStreamController::class, 'start']);
            $g->post('/{streamId}/end',    [LiveStreamController::class, 'end']);
            $g->get('/',                   [LiveStreamController::class, 'listActive']);
            $g->get('/{streamId}',         [LiveStreamController::class, 'show']);
            $g->post('/{streamId}/join',   [LiveStreamController::class, 'join']);
            $g->post('/{streamId}/leave',  [LiveStreamController::class, 'leave']);
            $g->get('/{streamId}/viewers', [LiveStreamController::class, 'viewers']);
            $g->post('/{streamId}/gift',   [LiveStreamController::class, 'sendGift']);
            $g->post('/{streamId}/co-host',[LiveStreamController::class, 'inviteCoHost']);
            $g->post('/schedule',          [LiveStreamController::class, 'schedule']);
            $g->get('/scheduled',          [LiveStreamController::class, 'listScheduled']);
            $g->get('/{streamId}/replay',  [LiveStreamController::class, 'replay']);
        }, [AuthMiddleware::class]);

        // ── Chat ──────────────────────────────────────────────────────────────
        $router->group('/api/v1/chat', function ($g) {
            $g->get('/conversations',          [ChatController::class, 'listConversations']);
            $g->get('/conversations/{convId}', [ChatController::class, 'getConversation']);
            $g->post('/conversations',         [ChatController::class, 'createConversation']);
            $g->get('/conversations/{convId}/messages', [ChatController::class, 'listMessages']);
            $g->post('/conversations/{convId}/messages',[ChatController::class, 'sendMessage'], [ModerationMiddleware::class]);
            $g->delete('/messages/{messageId}',         [ChatController::class, 'deleteMessage']);
            $g->post('/messages/{messageId}/react',     [ChatController::class, 'reactToMessage']);
            $g->post('/conversations/{convId}/read',    [ChatController::class, 'markAsRead']);
            $g->post('/conversations/{convId}/block',   [ChatController::class, 'blockConversation']);
            $g->get('/ws-token',                        [ChatController::class, 'wsAuthToken']);
        }, [AuthMiddleware::class]);

        // ── Friends / Social ──────────────────────────────────────────────────
        $router->group('/api/v1/friends', function ($g) {
            $g->get('/',                              [FriendController::class, 'list']);
            $g->post('/{userId}/follow',              [FriendController::class, 'follow']);
            $g->delete('/{userId}/follow',            [FriendController::class, 'unfollow']);
            $g->get('/{userId}/followers',            [FriendController::class, 'followers']);
            $g->get('/{userId}/following',            [FriendController::class, 'following']);
            $g->post('/{userId}/block',               [FriendController::class, 'block']);
            $g->delete('/{userId}/block',             [FriendController::class, 'unblock']);
            $g->get('/blocked',                       [FriendController::class, 'blockedList']);
            $g->get('/mutual/{userId}',               [FriendController::class, 'mutualFollowers']);
        }, [AuthMiddleware::class]);

        // ── Notifications ─────────────────────────────────────────────────────
        $router->group('/api/v1/notifications', function ($g) {
            $g->get('/',                          [NotificationController::class, 'list']);
            $g->post('/{notifId}/read',           [NotificationController::class, 'markRead']);
            $g->post('/read-all',                 [NotificationController::class, 'markAllRead']);
            $g->delete('/{notifId}',              [NotificationController::class, 'delete']);
            $g->get('/preferences',               [NotificationController::class, 'preferences']);
            $g->put('/preferences',               [NotificationController::class, 'updatePreferences']);
            $g->post('/push-token',               [NotificationController::class, 'registerPushToken']);
        }, [AuthMiddleware::class]);

        // ── Monetization: Gifts ───────────────────────────────────────────────
        $router->group('/api/v1/gifts', function ($g) {
            $g->get('/',                      [GiftController::class, 'listGiftCatalog']);
            $g->post('/send',                 [GiftController::class, 'sendGift']);
            $g->get('/received',              [GiftController::class, 'receivedGifts']);
            $g->get('/sent',                  [GiftController::class, 'sentGifts']);
            $g->get('/wallet',                [GiftController::class, 'wallet']);
            $g->post('/wallet/top-up',        [GiftController::class, 'topUpWallet']);
            $g->post('/wallet/withdraw',      [GiftController::class, 'withdrawEarnings']);
            $g->get('/leaderboard',           [GiftController::class, 'giftLeaderboard']);
        }, [AuthMiddleware::class]);

        // ── Monetization: Ads ─────────────────────────────────────────────────
        $router->group('/api/v1/ads', function ($g) {
            $g->get('/next',                  [AdController::class, 'nextAd']);
            $g->post('/{adId}/impression',    [AdController::class, 'recordImpression']);
            $g->post('/{adId}/click',         [AdController::class, 'recordClick']);
            $g->post('/{adId}/skip',          [AdController::class, 'recordSkip']);
            $g->get('/preferences',           [AdController::class, 'adPreferences']);
            $g->put('/preferences',           [AdController::class, 'updateAdPreferences']);
        }, [AuthMiddleware::class]);

        // ── Monetization: Creator Ads Manager ────────────────────────────────
        $router->group('/api/v1/creator/ads', function ($g) {
            $g->get('/',                  [AdController::class, 'creatorList']);
            $g->post('/',                 [AdController::class, 'createCampaign']);
            $g->get('/{campaignId}',      [AdController::class, 'campaignDetails']);
            $g->patch('/{campaignId}',    [AdController::class, 'updateCampaign']);
            $g->delete('/{campaignId}',   [AdController::class, 'deleteCampaign']);
            $g->get('/{campaignId}/stats',[AdController::class, 'campaignStats']);
        }, [AuthMiddleware::class]);

        // ── Payments ──────────────────────────────────────────────────────────
        $router->group('/api/v1/payments', function ($g) {
            $g->get('/methods',             [PaymentController::class, 'listMethods']);
            $g->post('/methods',            [PaymentController::class, 'addMethod']);
            $g->delete('/methods/{methodId}',[PaymentController::class, 'removeMethod']);
            $g->get('/history',             [PaymentController::class, 'history']);
            $g->get('/history/{txId}',      [PaymentController::class, 'transactionDetail']);
            $g->post('/stripe/intent',      [PaymentController::class, 'createPaymentIntent']);
            $g->post('/payout',             [PaymentController::class, 'requestPayout']);
            $g->get('/payout/status',       [PaymentController::class, 'payoutStatus']);
            $g->post('/subscribe',          [PaymentController::class, 'subscribe']);
            $g->delete('/subscribe',        [PaymentController::class, 'cancelSubscription']);
        }, [AuthMiddleware::class]);

        // Stripe webhook (no auth, verified via signature)
        $router->post('/api/v1/payments/webhook/stripe', [PaymentController::class, 'stripeWebhook']);

        // ── Moderation (Admin) ────────────────────────────────────────────────
        $router->group('/api/v1/moderation', function ($g) {
            $g->get('/reports',                   [ModerationController::class, 'listReports']);
            $g->get('/reports/{reportId}',        [ModerationController::class, 'reportDetail']);
            $g->post('/reports/{reportId}/review',[ModerationController::class, 'reviewReport']);
            $g->get('/queue',                     [ModerationController::class, 'moderationQueue']);
            $g->post('/content/{contentId}/ban',  [ModerationController::class, 'banContent']);
            $g->post('/content/{contentId}/restore',[ModerationController::class, 'restoreContent']);
            $g->post('/users/{userId}/warn',      [ModerationController::class, 'warnUser']);
            $g->post('/users/{userId}/suspend',   [ModerationController::class, 'suspendUser']);
            $g->post('/users/{userId}/ban',       [ModerationController::class, 'banUser']);
            $g->post('/users/{userId}/unban',     [ModerationController::class, 'unbanUser']);
            $g->get('/banned-words',              [ModerationController::class, 'bannedWords']);
            $g->post('/banned-words',             [ModerationController::class, 'addBannedWord']);
            $g->delete('/banned-words/{wordId}',  [ModerationController::class, 'removeBannedWord']);
            $g->get('/ai-flags',                  [ModerationController::class, 'aiFlaggedContent']);
        }, [AuthMiddleware::class, AdminMiddleware::class]);

        // ── Analytics ─────────────────────────────────────────────────────────
        $router->group('/api/v1/analytics', function ($g) {
            // Creator analytics (own content)
            $g->get('/overview',              [AnalyticsController::class, 'creatorOverview']);
            $g->get('/videos',                [AnalyticsController::class, 'videoAnalytics']);
            $g->get('/videos/{videoId}',      [AnalyticsController::class, 'videoDetail']);
            $g->get('/audience',              [AnalyticsController::class, 'audienceInsights']);
            $g->get('/growth',                [AnalyticsController::class, 'growthMetrics']);
            $g->get('/earnings',              [AnalyticsController::class, 'earningsSummary']);
            $g->get('/earnings/breakdown',    [AnalyticsController::class, 'earningsBreakdown']);
        }, [AuthMiddleware::class]);

        // ── Platform Analytics (Admin) ────────────────────────────────────────
        $router->group('/api/v1/admin/analytics', function ($g) {
            $g->get('/dau',                   [AnalyticsController::class, 'dailyActiveUsers']);
            $g->get('/mau',                   [AnalyticsController::class, 'monthlyActiveUsers']);
            $g->get('/retention',             [AnalyticsController::class, 'retentionCohorts']);
            $g->get('/revenue',               [AnalyticsController::class, 'revenueReport']);
            $g->get('/revenue/breakdown',     [AnalyticsController::class, 'revenueBreakdown']);
            $g->get('/content',               [AnalyticsController::class, 'contentReport']);
            $g->get('/live',                  [AnalyticsController::class, 'liveStreamReport']);
            $g->get('/regions',               [AnalyticsController::class, 'regionBreakdown']);
            $g->get('/export',                [AnalyticsController::class, 'exportReport']);
        }, [AuthMiddleware::class, AdminMiddleware::class]);

        // ── Admin ─────────────────────────────────────────────────────────────
        $router->group('/api/v1/admin', function ($g) {
            $g->get('/users',                   [AdminController::class, 'listUsers']);
            $g->get('/users/{userId}',          [AdminController::class, 'userDetail']);
            $g->get('/platform-config',         [AdminController::class, 'getPlatformConfig']);
            $g->put('/platform-config',         [AdminController::class, 'updatePlatformConfig']);
            $g->get('/gift-catalog',            [AdminController::class, 'giftCatalog']);
            $g->post('/gift-catalog',           [AdminController::class, 'addGift']);
            $g->put('/gift-catalog/{giftId}',   [AdminController::class, 'updateGift']);
            $g->get('/payout-requests',         [AdminController::class, 'listPayoutRequests']);
            $g->post('/payout-requests/{id}/approve', [AdminController::class, 'approvePayout']);
            $g->post('/payout-requests/{id}/reject',  [AdminController::class, 'rejectPayout']);
        }, [AuthMiddleware::class, AdminMiddleware::class]);
    }
}
