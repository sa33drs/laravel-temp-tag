<?php

namespace Imanghafoori\Tags\Console\Commands;

use Codino\User;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Imanghafoori\Tags\Models\TempTag;
use Illuminate\Support\Facades\Event;

class TestTempTags extends Command
{
    protected $signature = 'tag:test';

    protected $description = 'Delete expired temporary tag models.';

    protected $service;

    public function handle(): void
    {
        config(['database.default' => 'test_mysql']);
        TempTag::query()->delete();
        $user = new User;
        $user->id = 1;

# =================== test no tag =====================

        $res = [
            tempTags($user)->getExpiredTag('banned'),
            tempTags($user)->getTag('banned'),
            tempTags($user)->getActiveTag('banned'),
        ];
        assert($res === [null, null, null]);


# =================== test active tag =====================

        $tomorrow = Carbon::now()->addDay();
        tempTags($user)->tagIt('banned', $tomorrow);

        $res = [
            tempTags($user)->getExpiredTag('banned'),
            tempTags($user)->getTag('banned')->isActive(),
            tempTags($user)->getActiveTag('banned')->isActive(),
            tempTags($user)->getActiveTag('banned')->isPermanent()
        ];
        assert($res === [null, true, true, false]);

# =================== test expired tag =====================

        // travel through time
        Carbon::setTestNow(Carbon::now()->addDay()->addMinute());

        $res = [
            tempTags($user)->getExpiredTag('banned')->isActive(),
            tempTags($user)->getTag('banned')->isActive(),
            tempTags($user)->getActiveTag('banned'),
        ];
        assert($res === [false, false, null]);

# =================== test deleted tag =====================

        tempTags($user)->unTag('banned');
        $res = [
            tempTags($user)->getExpiredTag('banned'),
            tempTags($user)->getTag('banned'),
            tempTags($user)->getActiveTag('banned'),
        ];

        assert($res === [null, null, null]);

# =================== test deleted tag =====================

        tempTags($user)->tagIt(['banned', 'man', 'superman', 'covid19']);
        tempTags($user)->tagIt('covid19', Carbon::now()->subSeconds(1));
        tempTags($user)->unTag(['banned', 'man']);
        $res = [
            tempTags($user)->getExpiredTag('banned'),
            tempTags($user)->getTag('banned'),
            tempTags($user)->getActiveTag('banned'),

            tempTags($user)->getExpiredTag('man'),
            tempTags($user)->getTag('man'),
            tempTags($user)->getActiveTag('man'),

            tempTags($user)->getExpiredTag('covid19')->title,
            tempTags($user)->getTag('covid19')->title,
            tempTags($user)->getActiveTag('covid19'),

            tempTags($user)->getActiveTag('superman')->title,
        ];

        assert($res === [
                null,
                null,
                null,

                null,
                null,
                null,

                'covid19',
                'covid19',
                null,

                'superman',
            ]);

# =================== test deleted tag =====================

        tempTags($user)->tagIt('banned');
        tempTags($user)->unTag();
        $res = [
            tempTags($user)->getExpiredTag('banned'),
            tempTags($user)->getTag('banned'),
            tempTags($user)->getActiveTag('banned'),
        ];

        assert($res === [null, null, null]);

        tempTags($user)->tagIt('banned');
        tempTags($user)->unTag('manned');
        $res = [
            tempTags($user)->getExpiredTag('banned'),
            tempTags($user)->getTag('banned')->isActive(),
            tempTags($user)->getActiveTag('banned')->isActive(),
            tempTags($user)->getActiveTag('banned')->isPermanent(),
        ];

        assert($res === [null, true, true, true]);

# =================== test expire tag =====================

        tempTags($user)->expireNow('banned');
        $res = [
            tempTags($user)->getExpiredTag('banned')->title,
            tempTags($user)->getTag('banned')->isActive(),
            tempTags($user)->getActiveTag('banned'),
        ];

        assert($res === ['banned', false, null,]);

# ================== make permanent ======================

        $tags = tempTags($user)->tagIt('banned', Carbon::now()->addDay());
        tempTags($user)->getTag('banned')->expiresAt();

# ================== make permanent ======================
        tempTags($user)->unTag();
        tempTags($user)->tagIt(['banned']);
        Event::fake();
        tempTags($user)->tagIt(['rut'], Carbon::now()->subSecond());

        $actives = tempTags($user)->getAllActiveTags();
        $expired = tempTags($user)->getAllExpiredTags();
        $all = tempTags($user)->getAllTags();
        assert(($actives[0])->title === 'banned');
        assert(($expired[0])->title === 'rut');
        assert(count($all) === 2);
        Event::assertDispatched('tmp_tagged:users,rut');

        dd('Everything Is Ok.');
    }
}
