<?php

namespace Tests\Feature\Models;

use App\Enums\ContributionAction;
use App\Enums\ContributionStatus;
use App\Enums\LineEventType;
use App\Enums\LineType;
use App\Enums\MaterialType;
use App\Enums\MediaType;
use App\Enums\SegmentStatus;
use App\Enums\StationStatus;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Models\Contribution;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\LineGeometry;
use App\Models\LineMaterial;
use App\Models\LineSegment;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use App\Models\User;
use Carbon\CarbonInterface;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\MultiLineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;
use ValueError;

/**
 * Modèles Eloquent : relations (type et contenu), casts (énumérations, dates,
 * tableaux JSON, booléens, géométries Magellan), clés de route, attributs
 * cachés et affectation de masse.
 */
class ModelsTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    private function user(array $attributes = []): User
    {
        // Mot de passe en clair : le hash figé de UserFactory (coût 10) est refusé
        // par le cast « hashed » quand BCRYPT_ROUNDS=4.
        return User::factory()->create(['password' => 'secret-pass', ...$attributes]);
    }

    public function test_line_relations_casts_and_route_key(): void
    {
        $line = $this->line->fresh();

        $this->assertInstanceOf(HasMany::class, $line->segments());
        $this->assertInstanceOf(HasMany::class, $line->events());
        $this->assertInstanceOf(BelongsToMany::class, $line->materials());
        $this->assertInstanceOf(MorphMany::class, $line->media());

        $this->assertCount(2, $line->segments);
        $this->assertSame(['opening'], $line->events->map(fn ($e) => $e->type->value)->all());
        $this->assertSame('slug', $line->getRouteKeyName());
        $this->assertSame(LineType::Passengers, $line->type);

        $line->update(['alt_names' => ['Ligne de Saint-Germain', 'Ligne P'], 'advocacy' => ['kind' => 'threatened'], 'is_threatened' => 1]);
        $line = $line->fresh();
        $this->assertSame(['Ligne de Saint-Germain', 'Ligne P'], $line->alt_names);
        $this->assertSame(['kind' => 'threatened'], $line->advocacy);
        $this->assertTrue($line->is_threatened);

        // type est nullable (colonne non curée) : null reste null, pas d'enum.
        $line->update(['type' => null]);
        $this->assertNull($line->fresh()->type);
    }

    public function test_line_materials_pivot_carries_era_dates_and_notes(): void
    {
        $this->line->materials()->updateExistingPivot($this->material->id, [
            'era_id' => $this->currentEra->id, 'from_date' => '1985-01-01', 'to_date' => '2010-12-31', 'notes' => 'Banlieue Ouest',
        ]);

        $pivot = $this->line->materials()->first()->pivot;
        $this->assertSame($this->currentEra->id, (int) $pivot->era_id);
        $this->assertSame('Banlieue Ouest', $pivot->notes);
        $this->assertStringStartsWith('1985-01-01', (string) $pivot->from_date);
        $this->assertNotNull($pivot->created_at, 'withTimestamps');

        // Côté matériel, la même association.
        $this->assertSame([$this->line->slug], $this->material->lines()->pluck('slug')->all());

        $assoc = LineMaterial::firstOrFail();
        $this->assertInstanceOf(BelongsTo::class, $assoc->line());
        $this->assertSame($this->line->id, $assoc->line->id);
        $this->assertSame($this->material->id, $assoc->material->id);
        $this->assertSame(2030, $assoc->era->year);
        $this->assertInstanceOf(CarbonInterface::class, $assoc->from_date);
        $this->assertSame('2010-12-31', $assoc->to_date->toDateString());
        $this->assertSame([$assoc->id], $this->currentEra->lineMaterials()->pluck('id')->all());
    }

    public function test_line_segment_geometry_and_scalar_casts(): void
    {
        $segment = LineSegment::where('era_id', $this->currentEra->id)->firstOrFail();

        $this->assertInstanceOf(LineString::class, $segment->geom);
        $points = $segment->geom->getPoints();
        $this->assertCount(3, $points);
        $this->assertEqualsWithDelta(48.8763, $points[0]->getLatitude(), 1e-9);
        $this->assertEqualsWithDelta(2.3253, $points[0]->getLongitude(), 1e-9);
        $this->assertSame(SegmentStatus::InService, $segment->status);
        $this->assertTrue($segment->electrified);
        $this->assertFalse($segment->is_heritage);
        $this->assertSame(1500, $segment->voltage_v);
        $this->assertSame(120, $segment->max_speed_kmh);
        $this->assertSame($this->line->id, $segment->line->id);
        $this->assertSame(2030, $segment->era->year);
        $this->assertSame([$segment->id], $this->currentEra->segments()->pluck('id')->all());

        // Statut inconnu de l'enum : refusé dès l'écriture côté PHP.
        $this->expectException(ValueError::class);
        $segment->status = 'teleported';
    }

    public function test_era_casts(): void
    {
        $era = $this->currentEra->fresh();
        $era->update(['editable_until' => '2030-12-31', 'year' => '2030', 'is_current' => 1]);
        $era = $era->fresh();

        $this->assertSame(2030, $era->year);
        $this->assertTrue($era->is_current);
        $this->assertInstanceOf(CarbonInterface::class, $era->editable_until);
        $this->assertSame('2030-12-31', $era->editable_until->toDateString());
        $this->assertFalse($this->pastEra->fresh()->is_current);
    }

    public function test_line_event_casts_and_relations(): void
    {
        $event = LineEvent::firstOrFail();

        $this->assertSame(LineEventType::Opening, $event->type);
        $this->assertInstanceOf(CarbonInterface::class, $event->event_date);
        $this->assertSame('1839-08-02', $event->event_date->toDateString());
        $this->assertSame($this->line->id, $event->line->id);
        $this->assertInstanceOf(MorphMany::class, $event->media());
    }

    public function test_line_geometry_multilinestring(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO line_geometries (line_id, era_id, length_m, segment_count, part_count, geom, created_at, updated_at)
            VALUES (?, ?, '17250', '1', '2', ST_GeomFromText('MULTILINESTRING((2.3253 48.8763, 2.24 48.853), (2.20 48.83, 2.1347 48.8091))', 4326), now(), now())
            SQL, [$this->line->id, $this->currentEra->id]);

        $geometry = LineGeometry::firstOrFail();
        $this->assertInstanceOf(MultiLineString::class, $geometry->geom);
        $this->assertCount(2, $geometry->geom->getLineStrings());
        $this->assertSame(17_250, $geometry->length_m);
        $this->assertSame(1, $geometry->segment_count);
        $this->assertSame(2, $geometry->part_count);
        $this->assertSame($this->line->id, $geometry->line->id);
        $this->assertSame(2030, $geometry->era->year);
    }

    public function test_material_casts_relations_and_route_key(): void
    {
        $material = $this->material->fresh();

        $this->assertInstanceOf(MaterialType::class, $material->type);
        $this->assertSame('slug', $material->getRouteKeyName());
        $this->assertInstanceOf(MorphMany::class, $material->media());

        $material->update(['in_service_from' => '1985', 'in_service_to' => null]);
        $this->assertSame(1985, $material->fresh()->in_service_from);
        $this->assertNull($material->fresh()->in_service_to);
    }

    public function test_station_casts_relations_and_route_key(): void
    {
        $station = $this->origin->fresh();

        $this->assertInstanceOf(Point::class, $station->geom);
        $this->assertEqualsWithDelta(48.8763, $station->geom->getLatitude(), 1e-9);
        $this->assertSame(StationType::Gare, $station->type);
        $this->assertSame(StationStatus::Active, $station->status);
        $this->assertSame('1837-08-26', $station->opened_at->toDateString());
        $this->assertNull($station->closed_at);
        $this->assertSame('slug', $station->getRouteKeyName());

        $station->update(['alt_names' => ['Saint-Lazare'], 'provenance' => ['name' => 'sncf'], 'position_flagged' => 1]);
        $station = $station->fresh();
        $this->assertSame(['Saint-Lazare'], $station->alt_names);
        $this->assertSame(['name' => 'sncf'], $station->provenance);
        $this->assertTrue($station->position_flagged);

        $station->lines()->attach($this->line->id, ['method' => 'pk', 'pk_m' => 0, 'pk_source' => 'sncf', 'distance_m' => 3,
            'first_year' => 1837, 'last_year' => null, 'years_source' => 'wikipedia']);
        $pivot = $station->lines()->first()->pivot;
        $this->assertSame('pk', $pivot->method);
        $this->assertSame(1837, (int) $pivot->first_year);
        $this->assertSame(3, (int) $pivot->distance_m);
        $this->assertNotNull($pivot->updated_at);
    }

    public function test_media_casts_external_flag_and_scope(): void
    {
        $upload = Media::factory()->create(['mediable_type' => Line::class, 'mediable_id' => $this->line->id, 'type' => 'photo',
            'location' => Point::makeGeodetic(48.85, 2.24), 'exif_data' => ['iso' => 100]]);
        $commons = Media::factory()->create(['mediable_type' => Line::class, 'mediable_id' => $this->line->id, 'type' => 'postcard',
            'provider' => 'commons', 'external_id' => '42', 'file_path' => null, 'provider_meta' => ['date_precision' => 'circa'],
            'commercial_ok' => 1, 'share_alike' => 0, 'depicted_year' => '1905', 'is_featured' => 1]);

        $this->assertFalse($upload->isExternal());
        $this->assertTrue($commons->isExternal());
        $this->assertSame([$commons->id], Media::external()->pluck('id')->all());

        $commons = $commons->fresh();
        $this->assertSame(MediaType::Postcard, $commons->type);
        $this->assertSame(['date_precision' => 'circa'], $commons->provider_meta);
        $this->assertTrue($commons->commercial_ok);
        $this->assertFalse($commons->share_alike);
        $this->assertTrue($commons->is_featured);
        $this->assertSame(1905, $commons->depicted_year);

        $upload = $upload->fresh();
        $this->assertInstanceOf(Point::class, $upload->location);
        $this->assertEqualsWithDelta(2.24, $upload->location->getLongitude(), 1e-9);
        $this->assertSame(['iso' => 100], $upload->exif_data);
        $this->assertInstanceOf(CarbonInterface::class, $upload->captured_at);

        $this->assertInstanceOf(MorphTo::class, $upload->mediable());
        $this->assertTrue($upload->mediable->is($this->line));
        $this->assertSame(2, $this->line->media()->count());
        $this->assertSame('media', (new Media)->getTable());
    }

    public function test_user_hides_secrets_hashes_password_and_casts_role(): void
    {
        $user = $this->user(['role' => 'validator']);

        $this->assertSame(UserRole::Validator, $user->fresh()->role);
        $this->assertNotSame('secret-pass', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('secret-pass', $user->fresh()->password));

        $array = $user->fresh()->toArray();
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertStringNotContainsString('secret-pass', $user->toJson());
        $this->assertSame(0, $user->fresh()->reputation);
        $this->assertInstanceOf(CarbonInterface::class, $user->fresh()->email_verified_at);
    }

    public function test_user_mass_assignment_ignores_non_fillable_columns(): void
    {
        $user = User::create(['name' => 'Eve', 'email' => 'eve@example.org', 'password' => 'secret-pass',
            'reputation' => 9999, 'email_verified_at' => now(), 'id' => 4242]);

        $user = $user->fresh();
        $this->assertSame(0, $user->reputation, 'la réputation ne se fixe pas par affectation de masse');
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame(4242, $user->id);
    }

    public function test_contribution_casts_and_relations(): void
    {
        $author = $this->user();
        $reviewer = $this->user(['role' => 'validator']);
        $contribution = Contribution::create([
            'user_id' => $author->id, 'type' => 'line_segment', 'action' => 'update',
            'target_type' => Line::class, 'target_id' => $this->line->id, 'status' => 'approved',
            'payload' => ['era_year' => 2030, 'changes' => ['status' => 'abandoned']],
            'reviewed_by' => $reviewer->id, 'reviewed_at' => '2026-10-01 12:00:00',
        ])->fresh();

        $this->assertSame(ContributionAction::Update, $contribution->action);
        $this->assertSame(ContributionStatus::Approved, $contribution->status);
        // jsonb : l'ordre des clés n'est pas conservé, seul le contenu compte.
        $this->assertEquals(['era_year' => 2030, 'changes' => ['status' => 'abandoned']], $contribution->payload);
        $this->assertSame('2026-10-01 12:00:00', $contribution->reviewed_at->format('Y-m-d H:i:s'));
        $this->assertTrue($contribution->user->is($author));
        $this->assertTrue($contribution->reviewer->is($reviewer));
        $this->assertTrue($contribution->target->is($this->line));
        $this->assertSame([$contribution->id], $author->contributions()->pluck('id')->all());
        $this->assertSame([$contribution->id], $reviewer->reviewedContributions()->pluck('id')->all());
        $this->assertSame([], $reviewer->contributions()->pluck('id')->all());
    }

    public function test_guarded_id_cannot_be_mass_assigned(): void
    {
        foreach ([Line::class, Station::class, Era::class, Material::class, Contribution::class] as $model) {
            $this->assertSame(['id'], (new $model)->getGuarded(), $model);
        }
        $line = Line::create(['id' => 999_999, 'slug' => 'forcee', 'name' => 'Ligne forcée']);
        $this->assertNotSame(999_999, $line->id);
    }
}
