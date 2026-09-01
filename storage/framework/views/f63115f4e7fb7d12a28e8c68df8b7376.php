<?php
    $statePath = $getStatePath();
?>

<div
    x-data="{
        manualHotel: false,
        open: false,
        query: '',
        selected: $wire.$entangle(<?php echo \Illuminate\Support\Js::from($statePath)->toHtml() ?>).live,
        address: $wire.$entangle(<?php echo \Illuminate\Support\Js::from($statePath . '_address')->toHtml() ?>).live,
        mapsUrl: $wire.$entangle(<?php echo \Illuminate\Support\Js::from($statePath . '_google_maps_url')->toHtml() ?>).live,
        hotels: <?php echo \Illuminate\Support\Js::from($hotels)->toHtml() ?>,
        get results() {
            const needle = (this.query || '').toLocaleLowerCase();
            return this.hotels.filter(hotel => hotel.name.toLocaleLowerCase().includes(needle)).slice(0, 12);
        },
        choose(hotel) {
            this.selected = hotel.name;
            this.address = hotel.address || '';
            this.mapsUrl = hotel.google_maps_url || '';
            this.query = hotel.name;
            this.open = false;
        }
    }"
    x-init="query = selected || ''; const savedHotel = hotels.find(hotel => hotel.name === selected); if (savedHotel) { address = address || savedHotel.address || ''; mapsUrl = mapsUrl || savedHotel.google_maps_url || ''; }"
    style="display:flex; flex-direction:column; gap:.5rem;"
>
    <div x-show="!manualHotel" x-effect="if (!manualHotel) query = selected || ''">
        <label style="display:block; margin-bottom:.35rem; font-size:.78rem; font-weight:700; color:#475569;">🏢 <?php echo e(__('Rechercher dans la base d’hébergements')); ?></label>
        <div style="position:relative;">
            <input type="text" x-model="query" x-on:click.stop="open = true" x-on:focus="open = true" x-on:input="open = true" x-on:keydown.escape="open = false" placeholder="<?php echo e(__('Tapez le nom d’un hébergement…')); ?>" autocomplete="off" style="width:100%; padding:.6rem; border:1px solid #cbd5e1; border-radius:.375rem; font-size:.85rem; outline:none;">
            <div x-show="open" x-on:click.outside="open = false" x-cloak style="position:absolute; z-index:50; top:calc(100% - 1px); left:0; right:0; max-height:15rem; overflow-y:auto; border:1px solid #cbd5e1; border-radius:0 0 .5rem .5rem; background:#fff; box-shadow:0 10px 20px rgba(15,23,42,.12);">
                <template x-for="hotel in results" :key="hotel.name">
                    <button type="button" x-on:click="choose(hotel)" style="display:block; width:100%; padding:.65rem .75rem; border:0; border-bottom:1px solid #f1f5f9; background:#fff; text-align:left; cursor:pointer;">
                        <span x-text="hotel.name" style="display:block; color:#1e293b; font-size:.85rem; font-weight:700;"></span>
                        <span x-show="hotel.address" x-text="hotel.address" style="display:block; margin-top:.12rem; color:#64748b; font-size:.75rem;"></span>
                    </button>
                </template>
                <p x-show="results.length === 0" style="margin:0; padding:.75rem; color:#64748b; font-size:.8rem;"><?php echo e(__('Aucun hébergement correspondant')); ?></p>
            </div>
        </div>
        <a x-show="mapsUrl" x-bind:href="mapsUrl" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; gap:.35rem; margin-top:.55rem; color:#2563eb; font-size:.78rem; font-weight:700; text-decoration:underline;">📍 <?php echo e(__('Vérifier sur Google Maps')); ?></a>
        <button type="button" x-on:click="manualHotel = true" style="margin-top:.65rem; padding:.55rem .7rem; border:1px solid #99f6e4; border-radius:.45rem; background:#f0fdfa; color:#0f766e; font-size:.8rem; font-weight:800; text-align:left; cursor:pointer;">✏️ <?php echo e(__('L’hébergement n’est pas dans la liste ? Saisie manuelle')); ?></button>
    </div>

    <div x-show="manualHotel" style="display:none;">
        <label style="display:block; margin-bottom:.35rem; font-size:.78rem; font-weight:700; color:#475569;"><?php echo e(__('Nom de l’hébergement')); ?></label>
        <input type="text" x-model="selected" placeholder="<?php echo e($placeholder); ?>" style="width:100%; padding:.6rem; border:1px solid #cbd5e1; border-radius:.375rem; font-size:.85rem; outline:none;">
        <label style="display:block; margin:.65rem 0 .35rem; font-size:.78rem; font-weight:700; color:#475569;"><?php echo e(__('Adresse de l’hébergement')); ?></label>
        <textarea x-model="address" rows="2" placeholder="<?php echo e(__('Adresse complète (optionnel)')); ?>" style="width:100%; padding:.6rem; border:1px solid #cbd5e1; border-radius:.375rem; font-size:.85rem; outline:none; font-family:inherit;"></textarea>
        <label style="display:block; margin:.65rem 0 .35rem; font-size:.78rem; font-weight:700; color:#475569;"><?php echo e(__('Lien Google Maps')); ?> <span style="font-weight:500; color:#64748b;">(<?php echo e(__('optionnel')); ?>)</span></label>
        <input type="url" x-model="mapsUrl" placeholder="https://maps.google.com/..." style="width:100%; padding:.6rem; border:1px solid #cbd5e1; border-radius:.375rem; font-size:.85rem; outline:none;">
        <a x-show="mapsUrl" x-bind:href="mapsUrl" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; gap:.35rem; margin-top:.55rem; color:#2563eb; font-size:.78rem; font-weight:700; text-decoration:underline;">📍 <?php echo e(__('Vérifier sur Google Maps')); ?></a>
        <button type="button" x-on:click="manualHotel = false" style="margin-top:.65rem; padding:0; border:0; background:transparent; color:#096a61; font-size:.78rem; font-weight:700; text-align:left; cursor:pointer;"><?php echo e(__('Revenir à la recherche dans la base')); ?></button>
    </div>
</div>
<?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views\filament\forms\components\client-hotel-picker.blade.php ENDPATH**/ ?>