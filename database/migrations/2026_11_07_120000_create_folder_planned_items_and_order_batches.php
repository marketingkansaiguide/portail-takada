<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 3b — Préparation puis commande explicite des prestations internes.
 *
 * - folder_planned_items : prestations préparées (aucun automatisme ne lit cette table).
 * - folder_order_batches : traçabilité et idempotence des commandes.
 * - folder_items : lien unique vers la prestation préparée + suivi Calendar.
 * - Triggers : une prestation d'un dossier interne ne peut être créée qu'à partir
 *   d'une prestation préparée du même dossier ; l'origine d'un dossier est immuable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_order_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->string('selection_hash', 64);
            $table->json('planned_item_ids');
            $table->string('status', 20);            // completed
            $table->json('result')->nullable();      // état par ligne (commande + synchronisations externes)
            $table->json('snapshot')->nullable();    // prix préparés / retenus, sans données personnelles
            $table->boolean('price_changes_acknowledged')->default(false);
            $table->timestamps();
        });

        Schema::create('folder_planned_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->string('state', 20)->default('draft'); // draft | converted | discarded
            $table->string('source', 20)->default('manual'); // manual | quote (futur)
            $table->string('source_ref')->nullable();
            // Colonnes métier identiques à folder_items (voir PlannedItemMapper)
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_option_id')->nullable()->constrained('product_options')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('pax_adults')->default(0);
            $table->integer('pax_children')->default(0);
            $table->integer('pax_teenagers')->default(0);
            $table->integer('unit_price')->nullable();
            $table->integer('total_price')->nullable();
            $table->integer('purchase_unit_price')->nullable();
            $table->integer('purchase_total_price')->nullable();
            $table->json('custom_values')->nullable();
            $table->json('selected_options')->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_internal')->default(false);
            // Préparation et conversion
            $table->text('notes')->nullable();
            $table->timestamp('priced_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('folder_item_id')->nullable()->unique();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_batch_id')->nullable()->constrained('folder_order_batches')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->index(['folder_id', 'state']);
        });

        Schema::table('folder_items', function (Blueprint $table) {
            $table->foreignId('planned_item_id')->nullable()->unique()->constrained('folder_planned_items')->restrictOnDelete();
            $table->foreignId('ordered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_batch_id')->nullable()->constrained('folder_order_batches')->nullOnDelete();
            $table->string('calendar_sync_status', 20)->nullable();
            $table->text('calendar_sync_error')->nullable();
            $table->timestamp('calendar_synced_at')->nullable();
        });

        $this->createTriggers();
    }

    private function createTriggers(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE folder_planned_items ADD CONSTRAINT folder_planned_items_state_valid CHECK (state IN ('draft', 'converted', 'discarded'))");

            DB::unprepared(<<<'SQL'
CREATE TRIGGER folder_items_internal_requires_planned BEFORE INSERT ON folder_items FOR EACH ROW
BEGIN
    IF (SELECT origin FROM folders WHERE id = NEW.folder_id) = 'internal' THEN
        IF NEW.planned_item_id IS NULL OR NOT EXISTS (
            SELECT 1 FROM folder_planned_items p
            WHERE p.id = NEW.planned_item_id AND p.folder_id = NEW.folder_id AND p.state = 'draft'
        ) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Prestation interne : commande uniquement depuis une prestation preparee du meme dossier';
        END IF;
    END IF;
END
SQL);
            DB::unprepared(<<<'SQL'
CREATE TRIGGER folder_items_internal_no_move BEFORE UPDATE ON folder_items FOR EACH ROW
BEGIN
    IF NOT (NEW.folder_id <=> OLD.folder_id) OR NOT (NEW.planned_item_id <=> OLD.planned_item_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Le dossier et la prestation preparee d une ligne commandee ne peuvent pas changer';
    END IF;
END
SQL);
            DB::unprepared(<<<'SQL'
CREATE TRIGGER folders_origin_immutable BEFORE UPDATE ON folders FOR EACH ROW
BEGIN
    IF NOT (NEW.origin <=> OLD.origin) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'L origine d un dossier est immuable';
    END IF;
END
SQL);
        } elseif ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER folder_items_internal_requires_planned BEFORE INSERT ON folder_items
WHEN (SELECT origin FROM folders WHERE id = NEW.folder_id) = 'internal'
 AND (NEW.planned_item_id IS NULL OR NOT EXISTS (
        SELECT 1 FROM folder_planned_items p WHERE p.id = NEW.planned_item_id AND p.folder_id = NEW.folder_id AND p.state = 'draft'))
BEGIN
    SELECT RAISE(ABORT, 'Prestation interne : commande uniquement depuis une prestation preparee du meme dossier');
END
SQL);
            DB::unprepared(<<<'SQL'
CREATE TRIGGER folder_items_internal_no_move BEFORE UPDATE OF folder_id, planned_item_id ON folder_items
WHEN NEW.folder_id IS NOT OLD.folder_id OR NEW.planned_item_id IS NOT OLD.planned_item_id
BEGIN
    SELECT RAISE(ABORT, 'Le dossier et la prestation preparee d une ligne commandee ne peuvent pas changer');
END
SQL);
            DB::unprepared(<<<'SQL'
CREATE TRIGGER folders_origin_immutable BEFORE UPDATE OF origin ON folders
WHEN NEW.origin IS NOT OLD.origin
BEGIN
    SELECT RAISE(ABORT, 'L origine d un dossier est immuable');
END
SQL);
        }
    }

    public function down(): void
    {
        foreach (['folder_items_internal_requires_planned', 'folder_items_internal_no_move', 'folders_origin_immutable'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        if (DB::table('folder_items')->whereNotNull('planned_item_id')->exists()) {
            throw new RuntimeException('Des prestations ont été commandées depuis la préparation : restaurer la sauvegarde au lieu de revenir en arrière.');
        }

        Schema::table('folder_items', function (Blueprint $table) {
            $table->dropForeign(['planned_item_id']);
            $table->dropUnique(['planned_item_id']);
        });

        Schema::table('folder_items', function (Blueprint $table) {
            $table->dropColumn('planned_item_id');
            $table->dropConstrainedForeignId('ordered_by_user_id');
            $table->dropConstrainedForeignId('order_batch_id');
            $table->dropColumn(['calendar_sync_status', 'calendar_sync_error', 'calendar_synced_at']);
        });
        Schema::dropIfExists('folder_planned_items');
        Schema::dropIfExists('folder_order_batches');
    }
};
