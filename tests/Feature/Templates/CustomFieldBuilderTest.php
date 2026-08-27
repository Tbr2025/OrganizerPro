<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\TournamentCustomField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The registration form builder: field types, validation rules and conditional visibility.
 *
 * The load-bearing requirement is that none of this changes a tournament that already exists.
 * Every field created before these columns existed reads back validation = null,
 * conditions = null, condition_match = null, and must still produce exactly the rules it did
 * when they were hard-coded in RegistrationController.
 */
class CustomFieldBuilderTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function field(array $attrs = []): TournamentCustomField
    {
        $field = new TournamentCustomField();
        $field->fill(array_merge([
            'form' => 'player',
            'label' => 'Field',
            'type' => 'text',
            'section' => 'Basic Information',
            'required' => false,
            'visible' => true,
        ], $attrs));

        return $field;
    }

    #[Test]
    public function a_field_that_predates_this_feature_behaves_exactly_as_before(): void
    {
        // The three shapes RegistrationController used to hard-code, with the new columns null.
        $text = $this->field(['type' => 'text', 'required' => true]);
        $this->assertSame(['required', 'string', 'max:1000'], $text->validationRules());

        $number = $this->field(['type' => 'number', 'required' => false]);
        $this->assertSame(['nullable', 'numeric'], $number->validationRules());

        $checkbox = $this->field(['type' => 'checkbox', 'required' => true]);
        $this->assertSame(['accepted'], $checkbox->validationRules());

        $date = $this->field(['type' => 'date', 'required' => true]);
        $this->assertSame(['required', 'date'], $date->validationRules());

        // And with no conditions it is always shown, whatever the answers are.
        $this->assertTrue($text->isVisibleGiven([]));
        $this->assertTrue($text->isVisibleGiven(['anything' => 'at all']));
    }

    #[Test]
    public function conditions_combine_with_all_any_and_none(): void
    {
        $answers = ['7' => 'Bowler', '8' => '15', '9' => ['Spin', 'Medium'], 'jersey_name' => ''];

        $cases = [
            // [match, conditions, expected]
            ['all',  [['field' => '7', 'operator' => 'equals', 'value' => 'Bowler'], ['field' => '8', 'operator' => 'lt', 'value' => '16']], true],
            ['all',  [['field' => '7', 'operator' => 'equals', 'value' => 'Bowler'], ['field' => '8', 'operator' => 'gt', 'value' => '16']], false],
            ['any',  [['field' => '7', 'operator' => 'equals', 'value' => 'Keeper'], ['field' => '8', 'operator' => 'lt', 'value' => '16']], true],
            ['any',  [['field' => '7', 'operator' => 'equals', 'value' => 'Keeper'], ['field' => '8', 'operator' => 'gt', 'value' => '16']], false],
            ['none', [['field' => '7', 'operator' => 'equals', 'value' => 'Keeper']], true],
            ['none', [['field' => '7', 'operator' => 'equals', 'value' => 'Bowler']], false],
            // A multi-choice answer "is" X when X is among the ticked boxes.
            ['all',  [['field' => '9', 'operator' => 'equals', 'value' => 'Spin']], true],
            ['all',  [['field' => '9', 'operator' => 'not_equals', 'value' => 'Pace']], true],
            // Core form fields are addressable by their input name, not only custom fields.
            ['all',  [['field' => 'jersey_name', 'operator' => 'empty', 'value' => '']], true],
            ['all',  [['field' => 'jersey_name', 'operator' => 'filled', 'value' => '']], false],
            // An unfinished builder row is not a rule.
            ['all',  [['field' => '', 'operator' => 'equals', 'value' => 'x']], true],
        ];

        foreach ($cases as $i => [$match, $conditions, $expected]) {
            $field = $this->field(['conditions' => $conditions, 'condition_match' => $match]);
            $this->assertSame($expected, $field->isVisibleGiven($answers), "case #{$i} ({$match})");
        }
    }

    #[Test]
    public function a_hidden_required_field_is_not_validated_and_stores_nothing(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');

        $role = $tournament->customFields()->create([
            'form' => 'player', 'label' => 'Playing Role', 'type' => 'radio',
            'options' => ['Batter', 'Bowler'], 'section' => 'Basic Information',
            'required' => true, 'visible' => true, 'sort_order' => 1,
        ]);

        // Only asked of bowlers — and required when it is asked.
        $style = $tournament->customFields()->create([
            'form' => 'player', 'label' => 'Bowling Style', 'type' => 'text',
            'section' => 'Basic Information', 'required' => true, 'visible' => true, 'sort_order' => 2,
            'conditions' => [['field' => (string) $role->id, 'operator' => 'equals', 'value' => 'Bowler']],
            'condition_match' => 'all',
        ]);

        $fields = $tournament->customFields()->get();
        $controller = app(\App\Http\Controllers\Public\RegistrationController::class);

        $answersOf = function (array $input) use ($controller, $fields) {
            $request = \Illuminate\Http\Request::create('/x', 'POST', ['custom_fields' => $input]);
            $m = new \ReflectionMethod($controller, 'customFieldAnswers');
            $m->setAccessible(true);

            return [$request, $m->invoke($controller, $request, $fields)];
        };

        $rulesM = new \ReflectionMethod($controller, 'customFieldRules');
        $rulesM->setAccessible(true);
        $collectM = new \ReflectionMethod($controller, 'collectCustomFieldValues');
        $collectM->setAccessible(true);

        /*
         * A batter is never shown Bowling Style, so it must contribute no rules at all. Before
         * conditions existed this could not happen; with them, forgetting it means a required
         * question nobody was asked rejects the whole form with an error pointing at a field
         * that is not on the page.
         */
        [$batterReq, $batterAnswers] = $answersOf([$role->id => 'Batter']);
        $batterRules = $rulesM->invoke($controller, $fields, $batterAnswers);
        $this->assertArrayHasKey('custom_fields.' . $role->id, $batterRules);
        $this->assertArrayNotHasKey('custom_fields.' . $style->id, $batterRules);

        // A stale or hand-posted answer to a hidden question is not recorded either.
        [$sneakyReq, $sneakyAnswers] = $answersOf([$role->id => 'Batter', $style->id => 'Left-arm quick']);
        $stored = $collectM->invoke($controller, $sneakyReq, $fields, $sneakyAnswers, $tournament);
        $this->assertArrayNotHasKey('cf_' . $style->id, $stored);

        // A bowler is asked, so it is validated and stored.
        [$bowlerReq, $bowlerAnswers] = $answersOf([$role->id => 'Bowler', $style->id => 'Offspin']);
        $bowlerRules = $rulesM->invoke($controller, $fields, $bowlerAnswers);
        $this->assertContains('required', $bowlerRules['custom_fields.' . $style->id]);
        $storedBowler = $collectM->invoke($controller, $bowlerReq, $fields, $bowlerAnswers, $tournament);
        $this->assertSame('Offspin', $storedBowler['cf_' . $style->id]);
    }

    #[Test]
    public function multi_choice_answers_are_stored_as_a_list_and_shown_without_throwing(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');

        $formats = $tournament->customFields()->create([
            'form' => 'player', 'label' => 'Preferred Formats', 'type' => 'checkbox_group',
            'options' => ['T20', 'ODI', 'Test'], 'section' => 'Basic Information',
            'required' => false, 'visible' => true, 'sort_order' => 1,
            'validation' => ['min_choices' => 1],
        ]);

        $this->assertSame(['nullable', 'array', 'min:1'], $formats->validationRules());

        $controller = app(\App\Http\Controllers\Public\RegistrationController::class);
        $fields = $tournament->customFields()->get();

        $request = \Illuminate\Http\Request::create('/x', 'POST', [
            'custom_fields' => [$formats->id => ['T20', '', 'Test']],
        ]);
        $answersM = new \ReflectionMethod($controller, 'customFieldAnswers');
        $answersM->setAccessible(true);
        $collectM = new \ReflectionMethod($controller, 'collectCustomFieldValues');
        $collectM->setAccessible(true);

        $answers = $answersM->invoke($controller, $request, $fields);
        $stored = $collectM->invoke($controller, $request, $fields, $answers, $tournament);

        // Blanks dropped, order kept, stored as a real list.
        $this->assertSame(['T20', 'Test'], $stored['cf_' . $formats->id]);

        /*
         * And the admin read screens must survive it. `{{ $value }}` on an array is a TypeError
         * in PHP 8, so every screen showing custom answers would have 500ed the first time
         * anybody ticked two boxes.
         */
        $this->assertSame('T20, Test', $formats->displayValue(['T20', 'Test']));
        $this->assertNull($formats->displayValue([]));
    }

    #[Test]
    public function layout_only_types_collect_and_validate_nothing(): void
    {
        foreach (['heading', 'divider'] as $type) {
            $field = $this->field(['type' => $type, 'required' => true]);
            $this->assertTrue($field->isLayoutOnly());
            $this->assertSame([], $field->validationRules(), "{$type} must not be validated");
            $this->assertNull($field->displayValue('anything'));
        }
    }

    #[Test]
    public function the_builder_only_stores_rules_it_recognises(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');

        $user = $this->makeAuctionOperator($org, ['tournament.edit']);
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']));

        // A post shaped like the builder BEFORE this feature: no validation, conditions or help.
        $this->actingAs($user)->post(route('admin.tournaments.settings.custom-fields.store', $tournament), [
            'form' => 'player', 'label' => 'Legacy', 'type' => 'textarea',
            'section' => 'Basic Information', 'required' => '1', 'visible' => '1',
        ])->assertRedirect();

        $legacy = $tournament->customFields()->where('label', 'Legacy')->firstOrFail();
        $this->assertNull($legacy->validation);
        $this->assertNull($legacy->conditions);
        $this->assertNull($legacy->condition_match);
        $this->assertSame(['required', 'string', 'max:1000'], $legacy->validationRules());

        // A full post: empty rule boxes and unfinished condition rows are dropped, not stored.
        $this->actingAs($user)->post(route('admin.tournaments.settings.custom-fields.store', $tournament), [
            'form' => 'player', 'label' => 'Modern', 'type' => 'text',
            'section' => 'Basic Information', 'required' => '1', 'visible' => '1',
            'help_text' => 'Only for bowlers',
            'validation' => ['minlength' => '3', 'maxlength' => '40', 'min' => '', 'file_max_kb' => ''],
            'condition_match' => 'any',
            'conditions' => [
                ['field' => (string) $legacy->id, 'operator' => 'filled', 'value' => ''],
                ['field' => '', 'operator' => 'equals', 'value' => 'unfinished'],
            ],
        ])->assertRedirect();

        $modern = $tournament->customFields()->where('label', 'Modern')->firstOrFail();
        // Loose compare: these arrive as form strings and are only ever read back as numbers,
        // so pinning '3' vs 3 would make the test about PHP typing rather than about the builder.
        $this->assertEqualsCanonicalizing(['minlength' => '3', 'maxlength' => '40'], $modern->validation);
        $this->assertArrayNotHasKey('min', $modern->validation, 'An empty rule box must not be stored.');
        $this->assertArrayNotHasKey('file_max_kb', $modern->validation);
        $this->assertCount(1, $modern->conditions);
        $this->assertSame('any', $modern->condition_match);
        $this->assertSame('Only for bowlers', $modern->help_text);
    }
}
