<?php

namespace App\Http\Controllers;

use App\Models\FolderItem;
use App\Models\ItemStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class GuideMissionStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('services.guide_planning.callback_token');
        $provided = (string) $request->bearerToken();
        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $data = $request->validate([
            'mission_id' => ['required', 'integer', 'min:1'],
            'ticketing_order_item_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['confirmed', 'unavailable', 'cancelled'])],
        ]);

        $item = FolderItem::query()
            ->whereKey($data['ticketing_order_item_id'])
            ->where('guide_mission_id', $data['mission_id'])
            ->firstOrFail();
        $statusName = match ($data['status']) {
            'confirmed' => 'Confirmé',
            'unavailable' => 'Pas de disponibilité',
            'cancelled' => 'Annulé',
        };
        $status = ItemStatus::firstOrCreate(['name' => $statusName], ['color' => $data['status'] === 'confirmed' ? 'success' : 'danger']);
        // Cette mise à jour provient de Planning Guides : ne pas redéclencher
        // l'annulation vers WordPress dans l'observer de prestation.
        $item->updateQuietly(['item_status_id' => $status->id]);
        if ($data['status'] === 'cancelled') {
            $item->folder?->recalculateBillableTotal();
        }

        Log::channel('guide_integration')->info('Statut de mission reçu.', [
            'folder_id' => $item->folder_id,
            'folder_item_id' => $item->id,
            'mission_id' => (int) $data['mission_id'],
            'status' => $data['status'],
        ]);

        return response()->json(['success' => true]);
    }
}
