<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refonte de la fiche commerciale : référence devis et consignes billetterie sur le dossier,
 * référentiel hôtels enrichi (ville, noms alternatifs, catégories de chambres),
 * hébergements et vols structurés, voyageurs plus souples, notes billetterie par prestation.
 * Purement additive : aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->string('quote_reference', 120)->nullable();
            $table->text('ticketing_general_notes')->nullable();
            $table->boolean('ticketing_urgent')->default(false);
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->string('city')->nullable();
            $table->text('alt_names')->nullable();
        });

        Schema::create('hotel_room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['hotel_id', 'name']);
        });

        Schema::create('folder_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained('hotels')->nullOnDelete();
            $table->string('city')->nullable();
            $table->string('hotel_name');             // valeur conservée : reste lisible si la fiche hôtel change
            $table->text('address')->nullable();
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->unsignedSmallInteger('rooms')->default(1);
            $table->foreignId('room_type_id')->nullable()->constrained('hotel_room_types')->nullOnDelete();
            $table->string('room_category', 30)->nullable();    // double, twin, single, triple, family, suite, japanese, other
            $table->string('room_label')->nullable();            // libellé conservé / catégorie personnalisée
            $table->string('meal_plan', 20)->nullable();         // none, breakfast, half_board, full_board, other
            $table->string('status', 20)->default('to_book');   // to_book, pending, confirmed, booked_by_client, cancelled
            $table->string('booked_by', 10)->default('takada'); // takada, agency, client
            $table->string('confirmation_ref')->nullable();
            $table->json('passenger_ids')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('purchase_price')->nullable();
            $table->unsignedInteger('sale_price')->nullable();
            $table->timestamps();
            $table->index(['folder_id', 'check_in']);
        });

        Schema::create('folder_flight_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10)->default('other'); // outbound, return, other
            $table->string('airline')->nullable();
            $table->string('flight_number', 20)->nullable();
            $table->string('departure_airport', 60)->nullable();
            $table->string('arrival_airport', 60)->nullable();
            $table->dateTime('departure_local')->nullable();   // heure locale de l'aéroport de départ
            $table->string('departure_tz', 40)->nullable();
            $table->dateTime('arrival_local')->nullable();     // heure locale de l'aéroport d'arrivée
            $table->string('arrival_tz', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['folder_id', 'departure_local']);
        });

        Schema::table('folder_passengers', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->change();
            $table->string('nationality')->nullable()->change();
            $table->string('phone', 60)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_lead')->default(false);
            $table->text('special_notes')->nullable();
        });

        Schema::table('folder_planned_items', function (Blueprint $table) {
            $table->text('ticketing_notes')->nullable();
            $table->string('meeting_point', 500)->nullable();
            $table->string('meeting_time', 5)->nullable();
        });

        Schema::table('folder_items', function (Blueprint $table) {
            $table->text('ticketing_notes')->nullable();
            $table->string('meeting_point', 500)->nullable();
            $table->string('meeting_time', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('folder_items', fn (Blueprint $t) => $t->dropColumn(['ticketing_notes', 'meeting_point', 'meeting_time']));
        Schema::table('folder_planned_items', fn (Blueprint $t) => $t->dropColumn(['ticketing_notes', 'meeting_point', 'meeting_time']));
        Schema::table('folder_passengers', fn (Blueprint $t) => $t->dropColumn(['phone', 'email', 'is_lead', 'special_notes']));
        Schema::dropIfExists('folder_flight_segments');
        Schema::dropIfExists('folder_stays');
        Schema::dropIfExists('hotel_room_types');
        Schema::table('hotels', fn (Blueprint $t) => $t->dropColumn(['city', 'alt_names']));
        Schema::table('folders', fn (Blueprint $t) => $t->dropColumn(['quote_reference', 'ticketing_general_notes', 'ticketing_urgent']));
        // birth_date / nationality restent nullables : les rendre obligatoires échouerait sur des lignes saisies sans valeur.
    }
};
