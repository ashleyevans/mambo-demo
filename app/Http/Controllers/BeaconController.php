<?php

namespace App\Http\Controllers;

use App\Models\BeaconEvent;
use App\Models\BeaconVisit;
use App\Services\OneSignal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class BeaconController extends Controller
{
    /**
     * The number of rows shown in each dashboard table.
     */
    private const TABLE_LIMIT = 100;

    /**
     * Offer push copy variants, picked at random per send to keep the nudge
     * fresh. Each is a {title, subtitle, message} triple.
     *
     * @var list<array{title: string, subtitle: string, message: string}>
     */
    public const OFFER_VARIANTS = [
        [
            'title' => "Ashley, don't pay for that coffee ☕",
            'subtitle' => "You're walking past Starbucks — your next one's on us",
            'message' => 'Latte, Flat White, Frappuccino — walk in, claim it, walk out. No catch.',
        ],
        [
            'title' => 'Free Starbucks. Right now. 👀',
            'subtitle' => "Ashley, you're basically at the door",
            'message' => "Tap to claim, show the barista, enjoy. We've already covered it.",
        ],
        [
            'title' => "That's a £4 coffee you don't need to buy, Ashley",
            'subtitle' => 'Starbucks is right there — this one\'s free',
            'message' => 'Your choice of drink, on Mambo. Claim it before you walk in.',
        ],
        [
            'title' => 'Your free Starbucks is waiting ☕',
            'subtitle' => 'Two steps away, Ashley',
            'message' => "Latte, Flat White or Frapp — tap to claim, it's already paid.",
        ],
        [
            'title' => "Coffee o'clock — and it's on us, Ashley",
            'subtitle' => 'Spotted you near Starbucks',
            'message' => 'Free drink, any size. No points to spend, no strings. Just walk in.',
        ],
    ];

    public function __construct(
        private readonly OneSignal $oneSignal,
    ) {}

    /**
     * Display visits (paired enter/exit with dwell time) and the raw event log.
     */
    public function index(): Response
    {
        $visits = BeaconVisit::query()
            ->latest('id')
            ->limit(self::TABLE_LIMIT)
            ->get()
            ->map(fn (BeaconVisit $visit): array => [
                'id' => $visit->id,
                'major' => $visit->major,
                'minor' => $visit->minor,
                'store' => $this->resolveStore($visit->major, $visit->minor),
                'entered_at' => $visit->entered_at?->toIso8601String(),
                'exited_at' => $visit->exited_at?->toIso8601String(),
                'duration_seconds' => $visit->duration_seconds,
                'ongoing' => $visit->isOngoing(),
            ]);

        $events = BeaconEvent::query()
            ->latest('id')
            ->limit(self::TABLE_LIMIT)
            ->get()
            ->map(fn (BeaconEvent $event): array => [
                'id' => $event->id,
                'major' => $event->major,
                'minor' => $event->minor,
                'store' => $this->resolveStore($event->major, $event->minor),
                'type' => $event->type,
                'occurred_at' => $event->created_at?->toIso8601String(),
            ]);

        return Inertia::render('beacons/index', [
            'visits' => $visits,
            'events' => $events,
            'eventCount' => BeaconEvent::count(),
        ]);
    }

    /**
     * Resolve the store a beacon sits in from its major/minor identifier.
     *
     * @return array{name: string|null, logo: string|null}
     */
    private function resolveStore(int $major, int $minor): array
    {
        /** @var array{name?: string, logo?: string}|null $store */
        $store = config("beacons.stores.{$major}:{$minor}");

        return [
            'name' => $store['name'] ?? null,
            'logo' => isset($store['logo']) ? asset($store['logo']) : null,
        ];
    }

    /**
     * Record a device entering a beacon's range, open a visit, and push a
     * Starbucks offer.
     */
    public function enter(Request $request): JsonResponse
    {
        $event = $this->recordEvent($request, 'enter');

        $this->openVisit($event->major, $event->minor);

        $this->pushStarbucksOffer($event->major, $event->minor);

        return $this->eventResponse($event);
    }

    /**
     * Send an example offer push as if an enter were received, without
     * recording an event or opening a visit. Used by the dashboard demo button.
     */
    public function sendDemoOffer(): RedirectResponse
    {
        if (! $this->oneSignal->isConfigured()) {
            return back()->with('error', 'OneSignal is not configured, so no notification was sent.');
        }

        try {
            $this->sendStarbucksOffer(1, 1);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not send the example offer.');
        }

        return back()->with('success', 'Example Starbucks offer sent.');
    }

    /**
     * Record a device exiting a beacon's range and close the open visit,
     * capturing the dwell time.
     */
    public function exit(Request $request): JsonResponse
    {
        $event = $this->recordEvent($request, 'exit');

        $duration = $this->closeVisit($event->major, $event->minor);

        return $this->eventResponse($event, $duration);
    }

    /**
     * Validate the payload and persist a beacon event of the given type.
     */
    private function recordEvent(Request $request, string $type): BeaconEvent
    {
        $validated = $request->validate([
            'major' => ['required', 'integer'],
            'minor' => ['required', 'integer'],
        ]);

        return BeaconEvent::create([
            'major' => $validated['major'],
            'minor' => $validated['minor'],
            'type' => $type,
        ]);
    }

    /**
     * Open a visit for the beacon unless the device is already recorded inside.
     */
    private function openVisit(int $major, int $minor): void
    {
        BeaconVisit::query()->firstOrCreate(
            ['major' => $major, 'minor' => $minor, 'exited_at' => null],
            ['entered_at' => Carbon::now()],
        );
    }

    /**
     * Close the open visit for the beacon and return the dwell time in seconds,
     * or null when there is no visit in progress.
     */
    private function closeVisit(int $major, int $minor): ?int
    {
        $visit = BeaconVisit::query()
            ->open()
            ->where('major', $major)
            ->where('minor', $minor)
            ->latest('id')
            ->first();

        if ($visit === null) {
            return null;
        }

        $exitedAt = Carbon::now();
        $duration = (int) abs($visit->entered_at->diffInSeconds($exitedAt));

        $visit->update([
            'exited_at' => $exitedAt,
            'duration_seconds' => $duration,
        ]);

        return $duration;
    }

    /**
     * Build the JSON acknowledgement for a recorded beacon event.
     */
    private function eventResponse(BeaconEvent $event, ?int $duration = null): JsonResponse
    {
        return response()->json([
            'event' => $event->type,
            'major' => $event->major,
            'minor' => $event->minor,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Best-effort offer push for a real enter event — failures are reported but
     * never block the API response.
     */
    private function pushStarbucksOffer(int $major, int $minor): void
    {
        if (! $this->oneSignal->isConfigured()) {
            return;
        }

        try {
            $this->sendStarbucksOffer($major, $minor);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Send the demo Starbucks cashback offer, carrying the beacon ids through
     * as data so the app can deep link when the notification is opened.
     *
     * @throws ConnectionException
     */
    private function sendStarbucksOffer(int $major, int $minor): void
    {
        $variant = Arr::random(self::OFFER_VARIANTS);

        $this->oneSignal->send(
            $variant['title'],
            $variant['message'],
            ['major' => $major, 'minor' => $minor],
            'https://static.winamillion.com/images/starbucks.png',
            $variant['subtitle'],
        );
    }
}
