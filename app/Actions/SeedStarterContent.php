<?php

namespace App\Actions;

use App\Models\Team;
use App\Models\User;
use App\Support\DefaultFormations;

class SeedStarterContent
{
    /**
     * Give a user's active team starter formations and drills.
     */
    public function handle(User $user): void
    {
        $this->seed($user->activeTeam(), $user);
    }

    /**
     * Give a team starter formations and a drill library suited to a youth 5v5 flag team.
     */
    public function seed(Team $team, User $user): void
    {
        if (! $team->formations()->exists()) {
            foreach (DefaultFormations::all() as $formation) {
                $team->formations()->forceCreate([...$formation, 'user_id' => $user->id]);
            }
        }

        if (! $team->drills()->exists()) {
            foreach ($this->drills() as $drill) {
                $team->drills()->forceCreate([...$drill, 'user_id' => $user->id]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function drills(): array
    {
        return [
            ['name' => 'Tag game warm-up', 'category' => 'Warm-up', 'duration' => 8, 'players' => null, 'equipment' => 'Cones, flags',
                'instructions' => 'Mark a small box with cones. Everyone wears flags; pull a flag and that player does five jumping jacks, then rejoins.',
                'coaching_points' => 'Stay low, eyes up, change direction quickly.'],
            ['name' => 'High knees and shuffles', 'category' => 'Warm-up', 'duration' => 5, 'players' => null, 'equipment' => 'Cones',
                'instructions' => 'Line up on one sideline. Jog, high knees, side shuffle and backpedal to the opposite cone and back.',
                'coaching_points' => 'Light feet, stay balanced.'],
            ['name' => 'Flag pulling basics', 'category' => 'Defense', 'duration' => 8, 'players' => 6, 'equipment' => 'Flags, cones',
                'instructions' => 'Ball carrier jogs toward a defender through a 5-yard lane. Defender approaches, breaks down, pulls the flag and recovers.',
                'coaching_points' => 'Buzz feet, eyes on the belt, pull and show the flag.'],
            ['name' => 'Pursuit angles', 'category' => 'Defense', 'duration' => 8, 'players' => 8, 'equipment' => 'Flags, cones',
                'instructions' => 'Runner takes off down the sideline; defenders start from the middle and take an angle to cut off the runner.',
                'coaching_points' => 'Run to where the runner will be, not where they are.'],
            ['name' => 'Partner catching', 'category' => 'Catching', 'duration' => 6, 'players' => null, 'equipment' => 'Balls',
                'instructions' => 'Partners throw from 5 yards apart, then step back one yard every four catches.',
                'coaching_points' => 'Make a diamond with your hands, watch the ball into your hands.'],
            ['name' => 'Short routes: hitch, slant, out', 'category' => 'Routes', 'duration' => 10, 'players' => 6, 'equipment' => 'Balls, cones',
                'instructions' => 'Receivers line up at cones and run the called route on the snap. QB throws on time. Rotate after each rep.',
                'coaching_points' => 'Sharp cuts, eyes back to the QB quickly, catch away from the body.'],
            ['name' => 'QB and center exchange', 'category' => 'Ball handling', 'duration' => 6, 'players' => 2, 'equipment' => 'Balls',
                'instructions' => 'Center snaps to QB in pairs, ten reps each, then add a handoff.',
                'coaching_points' => 'Soft hands, pressure from the center, QB eyes up.'],
            ['name' => 'Handoff and open-lane running', 'category' => 'Running', 'duration' => 10, 'players' => 5, 'equipment' => 'Balls, cones, flags',
                'instructions' => 'QB hands off to the back, who runs through a lane of cones. Add a defender once the exchange is clean.',
                'coaching_points' => 'Pocket for the ball, two hands on the exchange, head up.'],
            ['name' => 'QB accuracy targets', 'category' => 'Passing', 'duration' => 8, 'players' => 4, 'equipment' => 'Balls, cones',
                'instructions' => 'QB throws to a stationary receiver at 5, 8 and 10 yards. Alternate left and right.',
                'coaching_points' => 'Step toward the target, follow through, throw to the chest.'],
            ['name' => 'Install new plays', 'category' => 'Team offense', 'duration' => 12, 'players' => 5, 'equipment' => 'Balls, cones, flags',
                'instructions' => 'Walk through the play, jog through at half speed, then run at full speed against no defense.',
                'coaching_points' => 'Everyone knows their own job first, then the whole picture.'],
            ['name' => 'Controlled scrimmage', 'category' => 'Scrimmage', 'duration' => 12, 'players' => 10, 'equipment' => 'Balls, flags, cones',
                'instructions' => 'Offense runs practiced plays against a live defense. Rotate players every three plays.',
                'coaching_points' => 'Praise effort and execution; stop to teach, then run it again.'],
        ];
    }
}
