<?php if (isset($component)) { $__componentOriginal166a02a7c5ef5a9331faf66fa665c256 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal166a02a7c5ef5a9331faf66fa665c256 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament-panels::components.page.index','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament-panels::page'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <style>
        .gallery-thumb { flex-shrink: 0; width: 84px; height: 112px; border-radius: 0.75rem; overflow: hidden; cursor: pointer; border: 3px solid transparent; opacity: 0.6; transition: all 0.2s ease-in-out; }
        .gallery-thumb:hover { opacity: 1; }
        .gallery-thumb.active { border-color: #d97706; opacity: 1; transform: scale(1.05); }

        .calendar-cell { min-height: 60px; border-radius: 0.5rem; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 0.25rem 0; border: 1px solid transparent; transition: all 0.15s ease-in-out; overflow: hidden; }
        .calendar-cell-available { background: #ffffff; border-color: #e2e8f0; cursor: pointer; }
        .calendar-cell-available:hover { border-color: #096a61; background: #f0fdfa; }
        .calendar-cell-selected { background: #096a61 !important; border-color: #096a61 !important; box-shadow: 0 4px 6px -1px rgba(9, 106, 97, 0.4); }
        .calendar-cell-disabled { background: #f8fafc; border-color: transparent; cursor: not-allowed; opacity: 0.4; }
        .calendar-day-number { font-weight: 700; font-size: 1rem; line-height: 1.1; }
        .calendar-price-text { font-size: 0.65rem; font-weight: 700; line-height: 1.1; margin-top: 0.2rem; white-space: nowrap; }

        .pax-tabs-row::-webkit-scrollbar { display: none; }
        .pax-tabs-row { -ms-overflow-style: none; scrollbar-width: none; }
        
        .pax-tab-item {
            flex: 1;
            min-width: 80px;
            padding: 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }
        .pax-tab-item:hover {
            border-color: #096a61;
            background: #f8fafc;
            color: #096a61;
        }
        .pax-tab-item-active {
            border-color: #096a61 !important;
            background: #f0fdfa !important;
            color: #096a61 !important;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-spin-custom { animation: spin 1s linear infinite; }
    </style>

    <div style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 2.5rem; font-family: system-ui, sans-serif;">
        
        <div style="grid-column: span 12 / span 12;" class="xl:col-span-8">
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <div wire:ignore style="display: flex; flex-direction: column; gap: 2rem;">
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #d97706; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            <span><?php echo e($product->category->name ?? 'Prestation Japonaise'); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($product->is_public) && !$product->is_public): ?>
                                <span style="background: #fee2e2; color: #991b1b; font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: 0.25rem; margin-left: 0.5rem;">Tarif Négocié / Privé</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <h1 style="font-size: 2.5rem; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; margin: 0;"><?php echo e($product->name); ?></h1>
                        
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->days_before_opening): ?>
                                <div style="background: #f0fdfa; border-left: 4px solid #0d9488; padding: 1rem; border-radius: 0 0.75rem 0.75rem 0; display: flex; align-items: center; gap: 0.75rem; color: #115e59; font-size: 0.9rem;">
                                    <svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <span><strong>Ouverture des ventes :</strong> Les réservations pour ce produit ne peuvent être confirmées que <strong><?php echo e($product->days_before_opening); ?> jours</strong> avant la date d'activité.</span>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->is_lottery): ?>
                                <div style="background: #fef2f2; border-left: 4px solid #e11d48; padding: 1rem; border-radius: 0 0.75rem 0.75rem 0; display: flex; align-items: center; gap: 0.75rem; color: #9f1239; font-size: 0.9rem;">
                                    <svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    <span><strong>Disponibilité très limitée :</strong> En raison d'un nombre de places restreint auprès de nos partenaires locaux, il est possible que la prestation ne puisse pas être obtenue.</span>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <?php $images = is_array($product->images) ? $product->images : []; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($images) > 0): ?>
                        <div x-data="{ mainImage: '<?php echo e(asset('storage/' . $images[0])); ?>' }" style="display: flex; flex-direction: column; gap: 1rem;">
                            <div style="border-radius: 1.25rem; overflow: hidden; height: 540px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); background: #f1f5f9;">
                                <img :src="mainImage" src="<?php echo e(asset('storage/' . $images[0])); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: opacity 0.3s ease-in-out;" alt="<?php echo e($product->name); ?>">
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($images) > 1): ?>
                                <div style="display: flex; gap: 0.75rem; overflow-x: auto; padding-bottom: 0.5rem;">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $images; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $img): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <div @click="mainImage = '<?php echo e(asset('storage/' . $img)); ?>'" class="gallery-thumb" :class="{ 'active': mainImage === '<?php echo e(asset('storage/' . $img)); ?>' }">
                                            <img src="<?php echo e(asset('storage/' . $img)); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="border-radius: 1.25rem; overflow: hidden; height: 540px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-weight: 500;">
                            Aucun visuel disponible
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div style="background: white; padding: 2.5rem; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                        <h2 style="font-size: 1.4rem; font-weight: 700; color: #096a61; margin-top:0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 12px; height: 12px; background: #d97706; border-radius: 50%;"></span>
                            Présentation de la prestation
                        </h2>
                        <div style="color: #475569; line-height: 1.8; font-size: 1.05rem;">
                            <?php echo $product->description; ?>

                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div style="grid-column: span 12 / span 12;" class="xl:col-span-4">
            <div style="position: sticky; top: 2rem; display: flex; flex-direction: column; gap: 1.5rem;">
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->activeAgencyId): ?>
                    <div style="background: white; border-radius: 1.25rem; padding: 2rem; border: 1px solid #f1f5f9; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.03);">
                        
                        
                        <div style="display: grid; grid-template-columns: 1fr 0.6fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'calendar-picker'; ?>wire:key="calendar-picker" style="display: flex; flex-direction: column; gap: 0.35rem;" 
                                 x-data="{ 
                                    open: false,
                                    currentDate: new Date(),
                                    map: <?php echo \Illuminate\Support\Js::from($this->calendarMap)->toHtml() ?>,
                                    selectedDate: <?php if ((object) ('serviceDate') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('serviceDate'->value()); ?>')<?php echo e('serviceDate'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('serviceDate'); ?>')<?php endif; ?>.live,
                                    days: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                                    get currentMonthName() {
                                        let formatter = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
                                        let formatted = formatter.format(this.currentDate);
                                        return formatted.charAt(0).toUpperCase() + formatted.slice(1);
                                    },
                                    get monthDays() {
                                        let year = this.currentDate.getFullYear();
                                        let month = this.currentDate.getMonth();
                                        let date = new Date(year, month, 1);
                                        let daysArray = [];
                                        
                                        let firstDay = date.getDay() === 0 ? 6 : date.getDay() - 1;
                                        for(let i=0; i<firstDay; i++) daysArray.push(null);
                                        
                                        let todayStr = new Date();
                                        todayStr.setMinutes(todayStr.getMinutes() - todayStr.getTimezoneOffset());
                                        let todayFormatted = todayStr.toISOString().split('T')[0];

                                        while (date.getMonth() === month) {
                                            let d = new Date(date);
                                            let dateStr = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                                            
                                            let isPast = dateStr < todayFormatted;
                                            let info = this.map[dateStr] || { available: false, price: null, is_on_demand: false };
                                            
                                            let dayInfo = Object.assign({}, info);
                                            if (isPast) dayInfo.available = false;

                                            daysArray.push({
                                                dateStr: dateStr,
                                                dayNumber: d.getDate(),
                                                info: dayInfo
                                            });
                                            date.setDate(date.getDate() + 1);
                                        }
                                        return daysArray;
                                    },
                                    nextMonth() { this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 1); },
                                    prevMonth() { this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() - 1, 1); },
                                    selectDate(day) {
                                        if (day && day.info.available) {
                                            this.selectedDate = day.dateStr;
                                            this.open = false;
                                        }
                                    }
                                 }">
                                
                                <label style="font-size: 0.85rem; font-weight: 700; color: #374151;">Date d'activité</label>
                                <button type="button" @click="open = true" style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; text-align: left; display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                                    <span x-text="selectedDate ? selectedDate.split('-').reverse().join('/') : 'Sélectionner...'" :style="!selectedDate && 'color: #9ca3af'"></span>
                                    <svg style="width: 16px; height: 16px; color: #9ca3af;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                </button>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['serviceDate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div x-show="open" style="display: none;">
                                    <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.7); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(4px);">
                                        <div @click.away="open = false" style="background: white; border-radius: 1.25rem; width: 100%; max-width: 440px; padding: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #1e293b;">Sélectionnez une date</h3>
                                                <button type="button" @click="open = false" style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a'" onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b'">
                                                    <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                                <button type="button" @click="prevMonth()" style="padding: 0.5rem; border-radius: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; cursor: pointer; color: #475569;">
                                                    <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                                                </button>
                                                <div x-text="currentMonthName" style="font-weight: 700; font-size: 1.1rem; color: #096a61; text-transform: capitalize;"></div>
                                                <button type="button" @click="nextMonth()" style="padding: 0.5rem; border-radius: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; cursor: pointer; color: #475569;">
                                                    <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                                </button>
                                            </div>
                                            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.25rem; margin-bottom: 0.5rem;">
                                                <template x-for="d in days">
                                                    <div x-text="d" style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-align: center;"></div>
                                                </template>
                                            </div>
                                            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.25rem;">
                                                <template x-for="day in monthDays">
                                                    <div class="calendar-cell"
                                                         :class="{
                                                             'calendar-cell-selected': day && day.info.available && selectedDate === day.dateStr,
                                                             'calendar-cell-available': day && day.info.available && selectedDate !== day.dateStr,
                                                             'calendar-cell-disabled': !day || !day.info.available
                                                         }"
                                                         @click="selectDate(day)">
                                                        <span x-show="day" class="calendar-day-number" x-text="day ? day.dayNumber : ''" :style="selectedDate === (day ? day.dateStr : '') ? 'color: white;' : 'color: #1e293b;'"></span>
                                                        <template x-if="day && day.info.available">
                                                            <div style="text-align: center; width: 100%;">
                                                                <template x-if="!day.info.is_on_demand && day.info.price">
                                                                    <span class="calendar-price-text" x-text="day.info.price + ' ¥'" :style="selectedDate === day.dateStr ? 'color: #ccfbf1;' : 'color: #096a61;'"></span>
                                                                </template>
                                                                <template x-if="!day.info.is_on_demand && !day.info.price">
                                                                    <span class="calendar-price-text" :style="selectedDate === day.dateStr ? 'color: #ccfbf1;' : 'color: #096a61;'">-</span>
                                                                </template>
                                                                <template x-if="day.info.is_on_demand">
                                                                    <span class="calendar-price-text" :style="selectedDate === day.dateStr ? 'color: #ffedd5;' : 'color: #d97706;'">Devis</span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                <label style="font-size: 0.85rem; font-weight: 700; color: #374151;">Pax</label>
                                <input type="number" wire:model.live.debounce.500ms="quantity" min="1" style="width: 100%; padding: 0.55rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; text-align: center;">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['quantity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>

                        
                        <?php
                            // Regroupement des déclinaisons obligatoires par nom de groupe
                            $requiredGrouped = $product->productOptions
                                ->filter(fn($o) => $o->is_required || !empty($o->group_name))
                                ->groupBy(fn($o) => !empty($o->group_name) ? $o->group_name : 'default_required');

                            // Options simples facultatives (sans groupe et non obligatoires)
                            $optionalOptions = $product->productOptions
                                ->filter(fn($o) => !$o->is_required && empty($o->group_name));
                        ?>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requiredGrouped->count() > 0): ?>
                            <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; margin-bottom: 1.5rem;">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $requiredGrouped; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupName => $groupOptions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php 
                                        $displayGroupName = ($groupName === 'default_required') ? 'Déclinaison' : $groupName;
                                        $selectedInGroup = $groupOptions->first(fn($o) => !empty($selectedOptions[$o->id]['enabled'])) ?? $groupOptions->first();
                                    ?>
                                    <div style="margin-bottom: 1rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                            <label style="font-size: 0.85rem; font-weight: 700; color: #374151; display: flex; align-items: center; gap: 0.35rem;">
                                                <span><?php echo e($displayGroupName); ?></span>
                                                <span style="color: #dc2626; font-size: 0.85rem;">*</span>
                                            </label>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedInGroup && ($selectedInGroup->price_modifier ?? 0) > 0): ?>
                                                <span style="font-size: 0.8rem; color: #096a61; font-weight: 700;">
                                                    + <?php echo e(number_format($selectedInGroup->price_modifier, 0, '.', ' ')); ?> ¥
                                                </span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>

                                        <select wire:change="selectGroupVariant('<?php echo e(addslashes($groupName)); ?>', $event.target.value)" 
                                                style="width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; outline: none; background: #ffffff; color: #1e293b; cursor: pointer; transition: border-color 0.2s;"
                                                onfocus="this.style.borderColor='#096a61';" onblur="this.style.borderColor='#d1d5db';">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $groupOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <?php $isSelected = !empty($selectedOptions[$option->id]['enabled']); ?>
                                                <option value="<?php echo e($option->id); ?>" <?php if($isSelected): ?> selected <?php endif; ?>>
                                                    <?php echo e($option->name); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($option->price_modifier ?? 0) > 0): ?> (+ <?php echo e(number_format($option->price_modifier, 0, '.', ' ')); ?> ¥) <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                    </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($optionalOptions->count() > 0): ?>
                            <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; margin-bottom: 1.5rem;">
                                <h4 style="font-size: 0.9rem; font-weight: 700; color: #1e293b; margin: 0 0 0.75rem 0;">Options complémentaires (facultatives) :</h4>
                                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $optionalOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'option-'.e($option->id).''; ?>wire:key="option-<?php echo e($option->id); ?>" style="background: #f8fafc; border: 1px solid #f1f5f9; padding: 0.75rem; border-radius: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                            <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                                                <input type="checkbox" wire:model.live="selectedOptions.<?php echo e($option->id); ?>.enabled" id="opt_<?php echo e($option->id); ?>" style="margin-top: 0.2rem; accent-color: #096a61;">
                                                <label for="opt_<?php echo e($option->id); ?>" style="font-size: 0.85rem; font-weight: 600; color: #374151; cursor: pointer; flex-grow: 1;">
                                                    <?php echo e($option->name); ?>

                                                    <span style="display: block; font-size: 0.8rem; color: #096a61; font-weight: 700; margin-top: 0.1rem;">+ <?php echo e(number_format($option->price_modifier ?? 0, 0, '.', ' ')); ?> ¥</span>
                                                </label>
                                            </div>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($selectedOptions[$option->id]['enabled'] ?? false) && $option->billing_type === 'manual'): ?>
                                                <div style="display: flex; align-items: center; gap: 0.5rem; background: white; padding: 0.35rem 0.5rem; border-radius: 0.375rem; border: 1px solid #e2e8f0; margin-left: 1.25rem;">
                                                    <span style="font-size: 0.75rem; color: #64748b;">Quantité :</span>
                                                    <input type="number" wire:model.live.debounce.300ms="selectedOptions.<?php echo e($option->id); ?>.quantity" min="1" style="width: 50px; border: none; padding: 0; outline: none; text-align: center; font-size: 0.85rem; font-weight: 600;">
                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($product->custom_field_definitions) && count($product->custom_field_definitions) > 0): ?>
                            <?php
                                $globalFields = [];
                                $paxFields = [];
                                foreach($product->custom_field_definitions as $def) {
                                    if ($def['is_per_passenger'] ?? false) {
                                        $paxFields[] = $def;
                                    } else {
                                        $globalFields[] = $def;
                                    }
                                }
                                $qty = intval($quantity) > 0 ? intval($quantity) : 1;
                            ?>

                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem;">
                                <h4 style="font-size: 0.85rem; font-weight: 800; color: #096a61; margin: 0 0 0.75rem 0; text-transform: uppercase; letter-spacing: 0.05em;">Informations requises</h4>
                                
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($globalFields) > 0): ?>
                                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $globalFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $def): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <?php 
                                                    $key = !empty($def['key']) ? $def['key'] : \Illuminate\Support\Str::slug($def['name'] ?? 'custom', '_');
                                                    $isRequired = $def['is_required'] ?? false;
                                                    $modelKey = "customValues.{$key}";
                                                ?>
                                                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'global-field-'.e($key).''; ?>wire:key="global-field-<?php echo e($key); ?>" style="grid-column: span <?php echo e(in_array($def['type'], ['textarea', 'file']) ? '2' : '1'); ?>; display: flex; flex-direction: column; gap: 0.25rem;">
                                                    <label style="font-size: 0.85rem; font-weight: 600; color: #374151;">
                                                        <?php echo e($def['name']); ?> <?php echo $isRequired ? '<span style="color:#dc2626;">*</span>' : ''; ?>

                                                    </label>

                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($def['type'] === 'textarea'): ?>
                                                        <textarea wire:model="<?php echo e($modelKey); ?>" placeholder="<?php echo e($def['placeholder'] ?? ''); ?>" rows="2" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none; font-family:inherit;"></textarea>
                                                    <?php elseif($def['type'] === 'select'): ?>
                                                        <select wire:model="<?php echo e($modelKey); ?>" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none; background:white;">
                                                            <option value=""><?php echo e($def['placeholder'] ?? 'Sélectionnez...'); ?></option>
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $def['choices'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $choice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                                <option value="<?php echo e($choice); ?>"><?php echo e($choice); ?></option>
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                        </select>
                                                    <?php elseif($def['type'] === 'date'): ?>
                                                        <input type="date" wire:model="<?php echo e($modelKey); ?>" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                    <?php elseif($def['type'] === 'number'): ?>
                                                        <input type="number" wire:model="<?php echo e($modelKey); ?>" placeholder="<?php echo e($def['placeholder'] ?? ''); ?>" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                    <?php elseif($def['type'] === 'toggle'): ?>
                                                        <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.25rem;">
                                                            <input type="checkbox" wire:model="<?php echo e($modelKey); ?>" id="check_global_<?php echo e($key); ?>" style="width:18px; height:18px; accent-color:#096a61;">
                                                            <label for="check_global_<?php echo e($key); ?>" style="font-size:0.85rem; color:#4b5563;">Oui, je confirme</label>
                                                        </div>
                                                    <?php elseif($def['type'] === 'file'): ?>
                                                        <div x-data="{ isUploading: false, isUploaded: false, fileName: '' }"
                                                             x-on:livewire-upload-start="isUploading = true"
                                                             x-on:livewire-upload-finish="isUploading = false; isUploaded = true"
                                                             x-on:livewire-upload-error="isUploading = false"
                                                             style="position: relative; margin-top: 0.25rem;">

                                                            <label style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; border: 2px dashed #cbd5e1; border-radius: 0.75rem; padding: 1.25rem 1rem; background: #ffffff; cursor: pointer; transition: all 0.2s ease-in-out;"
                                                                   onmouseover="this.style.borderColor='#096a61'; this.style.background='#f0fdfa';"
                                                                   onmouseout="this.style.borderColor='#cbd5e1'; this.style.background='#ffffff';">

                                                                <div x-show="!isUploading && !isUploaded" style="display: flex; flex-direction: column; align-items: center; gap: 0.35rem; text-align: center;">
                                                                    <svg style="width: 28px; height: 28px; color: #096a61;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                                    </svg>
                                                                    <span style="font-size: 0.85rem; font-weight: 600; color: #334155;">Cliquez ou glissez un fichier ici</span>
                                                                    <span style="font-size: 0.75rem; color: #94a3b8;">PDF, JPG, PNG (Max. 10 Mo)</span>
                                                                </div>

                                                                <div x-show="isUploading" style="display: flex; align-items: center; gap: 0.5rem; color: #096a61; font-size: 0.85rem; font-weight: 600;">
                                                                    <svg class="animate-spin-custom" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24">
                                                                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                    </svg>
                                                                    <span>Transfert en cours...</span>
                                                                </div>

                                                                <div x-show="isUploaded && !isUploading" style="display: flex; align-items: center; gap: 0.5rem; color: #166534; font-size: 0.85rem; font-weight: 700;">
                                                                    <svg style="width: 22px; height: 22px; color: #16a34a;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                                    </svg>
                                                                    <span x-text="fileName ? 'Fichier prêt : ' + fileName : 'Document transmis avec succès !'"></span>
                                                                </div>

                                                                <input type="file"
                                                                       wire:model="<?php echo e($modelKey); ?>"
                                                                       accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"
                                                                       x-on:change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''"
                                                                       style="display: none;">
                                                            </label>

                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$modelKey];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:#dc2626; font-size:0.75rem; font-weight:500; margin-top:0.25rem; display:block;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <input type="text" wire:model="<?php echo e($modelKey); ?>" placeholder="<?php echo e($def['placeholder'] ?? ''); ?>" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$modelKey];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:#dc2626; font-size:0.75rem; font-weight:500;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($paxFields) > 0): ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($globalFields) > 0): ?>
                                            <div style="height: 1px; background: #e2e8f0; margin: 0.25rem 0;"></div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        
                                        <div x-data="{ activeTab: 0 }" style="display: flex; flex-direction: column; gap: 0.75rem;">
                                            
                                            <div class="pax-tabs-row" style="display: flex; gap: 0.4rem; overflow-x: auto; padding: 2px 0;">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php for($i = 0; $i < $qty; $i++): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <button type="button" 
                                                            @click="activeTab = <?php echo e($i); ?>" 
                                                            class="pax-tab-item"
                                                            :class="{ 'pax-tab-item-active': activeTab === <?php echo e($i); ?> }">
                                                        <svg width="14" height="14" style="width: 14px !important; height: 14px !important; flex-shrink: 0 !important; opacity: 0.75; display: block;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                                        <span>Pax <?php echo e($i + 1); ?></span>
                                                    </button>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </div>

                                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.02);">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php for($i = 0; $i < $qty; $i++): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <div x-show="activeTab === <?php echo e($i); ?>" style="display:none; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;" :style="activeTab === <?php echo e($i); ?> ? 'display:grid;' : 'display:none;'">
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $paxFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $def): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                            <?php 
                                                                $key = !empty($def['key']) ? $def['key'] : \Illuminate\Support\Str::slug($def['name'] ?? 'custom', '_');
                                                                $isRequired = $def['is_required'] ?? false;
                                                                $modelKey = "customValues.{$key}.{$i}";
                                                            ?>
                                                            
                                                            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'field-'.e($key).'-'.e($i).''; ?>wire:key="field-<?php echo e($key); ?>-<?php echo e($i); ?>" style="grid-column: span <?php echo e(in_array($def['type'], ['textarea', 'file']) ? '2' : '1'); ?>; display: flex; flex-direction: column; gap: 0.25rem;">
                                                                <label style="font-size: 0.85rem; font-weight: 600; color: #374151;">
                                                                    <?php echo e($def['name']); ?> <?php echo $isRequired ? '<span style="color:#dc2626;">*</span>' : ''; ?>

                                                                </label>

                                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($def['type'] === 'textarea'): ?>
                                                                    <textarea wire:model="<?php echo e($modelKey); ?>" placeholder="<?php echo e($def['placeholder'] ?? ''); ?>" rows="2" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none; font-family:inherit;"></textarea>
                                                                <?php elseif($def['type'] === 'select'): ?>
                                                                    <select wire:model="<?php echo e($modelKey); ?>" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none; background:white;">
                                                                        <option value=""><?php echo e($def['placeholder'] ?? 'Sélectionnez...'); ?></option>
                                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $def['choices'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $choice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                                            <option value="<?php echo e($choice); ?>"><?php echo e($choice); ?></option>
                                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                                    </select>
                                                                <?php elseif($def['type'] === 'date'): ?>
                                                                    <input type="date" wire:model="<?php echo e($modelKey); ?>" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                                <?php elseif($def['type'] === 'number'): ?>
                                                                    <input type="number" wire:model="<?php echo e($modelKey); ?>" placeholder="<?php echo e($def['placeholder'] ?? ''); ?>" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                                <?php elseif($def['type'] === 'toggle'): ?>
                                                                    <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.25rem;">
                                                                        <input type="checkbox" wire:model="<?php echo e($modelKey); ?>" id="check_pax_<?php echo e($key); ?>_<?php echo e($i); ?>" style="width:14px; height:14px; accent-color:#096a61;">
                                                                        <label for="check_pax_<?php echo e($key); ?>_<?php echo e($i); ?>" style="font-size:0.85rem; color:#4b5563;">Oui, je confirme</label>
                                                                    </div>
                                                                <?php elseif($def['type'] === 'file'): ?>
                                                                    <div x-data="{ isUploading: false, isUploaded: false, fileName: '' }"
                                                                         x-on:livewire-upload-start="isUploading = true"
                                                                         x-on:livewire-upload-finish="isUploading = false; isUploaded = true"
                                                                         x-on:livewire-upload-error="isUploading = false"
                                                                         style="position: relative; margin-top: 0.25rem;">

                                                                        <label style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; border: 2px dashed #cbd5e1; border-radius: 0.75rem; padding: 1.25rem 1rem; background: #ffffff; cursor: pointer; transition: all 0.2s ease-in-out;"
                                                                               onmouseover="this.style.borderColor='#096a61'; this.style.background='#f0fdfa';"
                                                                               onmouseout="this.style.borderColor='#cbd5e1'; this.style.background='#ffffff';">

                                                                            <div x-show="!isUploading && !isUploaded" style="display: flex; flex-direction: column; align-items: center; gap: 0.35rem; text-align: center;">
                                                                                <svg style="width: 28px; height: 28px; color: #096a61;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                                                </svg>
                                                                                <span style="font-size: 0.85rem; font-weight: 600; color: #334155;">Cliquez ou glissez le document du Pax <?php echo e($i + 1); ?></span>
                                                                                <span style="font-size: 0.75rem; color: #94a3b8;">PDF, JPG, PNG (Max. 10 Mo)</span>
                                                                            </div>

                                                                            <div x-show="isUploading" style="display: flex; align-items: center; gap: 0.5rem; color: #096a61; font-size: 0.85rem; font-weight: 600;">
                                                                                <svg class="animate-spin-custom" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24">
                                                                                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                                </svg>
                                                                                <span>Transfert en cours...</span>
                                                                            </div>

                                                                            <div x-show="isUploaded && !isUploading" style="display: flex; align-items: center; gap: 0.5rem; color: #166534; font-size: 0.85rem; font-weight: 700;">
                                                                                <svg style="width: 22px; height: 22px; color: #16a34a;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                                                </svg>
                                                                                <span x-text="fileName ? 'Fichier prêt : ' + fileName : 'Document transmis avec succès !'"></span>
                                                                            </div>

                                                                            <input type="file"
                                                                                   wire:model="<?php echo e($modelKey); ?>"
                                                                                   accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"
                                                                                   x-on:change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''"
                                                                                   style="display: none;">
                                                                        </label>

                                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$modelKey];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:#dc2626; font-size:0.75rem; font-weight:500; margin-top:0.25rem; display:block;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <input type="text" wire:model="<?php echo e($modelKey); ?>" placeholder="<?php echo e($def['placeholder'] ?? ''); ?>" style="width:100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size:0.85rem; outline:none;">
                                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$modelKey];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color:#dc2626; font-size:0.75rem; font-weight:500;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                            </div>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                    </div>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <div style="border-top: 1px solid #f1f5f9; padding-top: 1.5rem; margin-bottom: 1.5rem;">
                            <h4 style="font-size: 0.9rem; font-weight: 700; color: #1e293b; margin: 0 0 1rem 0;">Récapitulatif :</h4>
                            <?php
                                $estimate = $this->getEstimatedPrice();
                            ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($estimate['is_on_demand']): ?>
                                <div style="background: #fff7ed; border: 1px solid #ffedd5; color: #c2410c; padding: 1.5rem; border-radius: 0.75rem; text-align: center;">
                                    <h4 style="margin:0 0 0.5rem 0; font-size: 1.1rem; font-weight: 800;">⚠️ Tarif sur Devis</h4>
                                    <p style="margin:0; font-size: 0.85rem; opacity: 0.9;">Cette prestation nécessite une cotation personnalisée.</p>
                                </div>
                            <?php else: ?>
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.5rem; display: flex; flex-direction: column; box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.02);">
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">
                                            Prestation <?php echo e($estimate['has_date'] ? '('.$estimate['qty'].' pax)' : '(À partir de)'); ?>

                                        </span>
                                        <span style="font-size: 0.9rem; font-weight: 700; color: #334155;"><?php echo e(number_format($estimate['total_base'], 0, '.', ' ')); ?> ¥</span>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; min-height: 1.25rem; margin-bottom: 0.5rem;">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($estimate['total_options'] > 0): ?>
                                            <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Déclinaisons / Options</span>
                                            <span style="font-size: 0.9rem; font-weight: 700; color: #334155;">+ <?php echo e(number_format($estimate['total_options'], 0, '.', ' ')); ?> ¥</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    
                                    <div style="height: 1px; background: #e2e8f0; margin-bottom: 0.75rem;"></div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                                        <span style="font-size: 1rem; font-weight: 800; color: #1e293b; text-transform: uppercase;">Total</span>
                                        <span style="font-size: 2.25rem; font-weight: 800; color: #096a61; line-height: 1; letter-spacing: -0.02em;">
                                            <?php echo e(number_format($estimate['grand_total'], 0, '.', ' ')); ?> ¥
                                        </span>
                                    </div>
                                    
                                    <div style="min-height: <?php echo e($estimate['has_date'] ? '0' : '4rem'); ?>; transition: min-height 0.2s;">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$estimate['has_date']): ?>
                                            <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #d97706; background: #fffbeb; padding: 0.6rem; border-radius: 0.5rem; display: flex; align-items: center; gap: 0.5rem; line-height: 1.4;">
                                                <svg style="width:20px; height:20px; flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                Veuillez sélectionner une date d'activité pour obtenir le tarif exact.
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        
                        <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1rem;">
                            <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <label style="font-size: 0.85rem; font-weight: 700; color: #374151;">Dossier de destination</label>
                                    <?php echo e($this->createFolderAction); ?>

                                </div>
                                
                                <select wire:model="selectedFolderId" style="width: 100%; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; color: #111827;">
                                    <option value="">-- Choisir un dossier voyage --</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->foldersList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $folder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <option value="<?php echo e($folder->id); ?>">
                                            <?php echo e($folder->folder_name); ?> 
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($folder->start_date && $folder->end_date): ?>
                                                (Du <?php echo e(\Carbon\Carbon::parse($folder->start_date)->format('d/m/Y')); ?> au <?php echo e(\Carbon\Carbon::parse($folder->end_date)->format('d/m/Y')); ?>)
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['selectedFolderId'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <button type="button" wire:click="addToFolder" style="width: 100%; background: #096a61; color: white; border: none; padding: 0.9rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.9rem; cursor: pointer; text-align: center; box-shadow: 0 4px 6px -1px rgba(9, 106, 97, 0.2); transition: background 0.2s;" onmouseover="this.style.background='#07534c'" onmouseout="this.style.background='#096a61'">
                                AJOUTER AU DOSSIER
                            </button>
                        </div>

                        
                        <?php
                            $isSpecific = $product->cancellation_type === 'specific' && !empty($product->cancellation_specifics);
                            $generalPolicy = \App\Models\Setting::first()?->general_cancellation_policy 
                                          ?? \App\Models\Setting::first()?->cancellation_policy 
                                          ?? '';
                            $hasPolicyToDisplay = $isSpecific || !empty($generalPolicy);
                            $policyContent = $isSpecific ? $product->cancellation_specifics : $generalPolicy;
                        ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasPolicyToDisplay): ?>
                        <div x-data="{ expanded: false }" style="background: white; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.03); overflow: hidden;">
                            <button @click="expanded = !expanded" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; background: transparent; border: none; cursor: pointer; text-align: left; outline: none;">
                                <span style="font-size: 0.95rem; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 0.5rem;">
                                    <svg width="20" height="20" style="width: 20px !important; height: 20px !important; flex-shrink: 0 !important; color: #94a3b8; display: block;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Détails des conditions d'annulation
                                </span>
                                <svg width="20" height="20" :style="expanded ? 'transform: rotate(180deg); width: 20px !important; height: 20px !important; flex-shrink: 0 !important; color: #64748b; transition: transform 0.2s; display: block;' : 'width: 20px !important; height: 20px !important; flex-shrink: 0 !important; color: #64748b; transition: transform 0.2s; display: block;'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            
                            <div x-show="expanded" x-collapse style="display: none;">
                                <div style="padding: 0 1.5rem 1.5rem 1.5rem;">
                                    <div style="background: #f8fafc; border-left: 4px solid #cbd5e1; padding: 1rem; border-radius: 0 0.5rem 0.5rem 0;">
                                        <div style="color: #475569; line-height: 1.6; font-size: 0.85rem; white-space: pre-wrap;"><?php echo $policyContent; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </div>
                
                <?php elseif($this->isAdmin): ?>
                    <div style="background: white; border-radius: 1.25rem; padding: 2.25rem 2rem; border: 1px solid #e0f2fe; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.04);">
                        <div style="color: #0284c7; background: #e0f2fe; width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto; border: 1px solid #bae6fd;">
                            <svg style="width: 26px; height: 26px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <h3 style="font-size: 1.3rem; font-weight: 800; color: #0f172a; margin: 0 0 0.5rem 0;">Vue Administrateur</h3>
                        <p style="color: #475569; font-size: 0.9rem; line-height: 1.6; margin: 0;">Vous explorez le catalogue avec un compte administrateur. Pour utiliser la fonction d'ajout au panier, veuillez vous connecter avec un véritable compte Agence partenaire.</p>
                    </div>

                <?php else: ?>
                    <div style="background: white; border-radius: 1.25rem; padding: 2.25rem 2rem; border: 1px solid #f1f5f9; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.04);">
                        <div style="color: #d97706; background: #fffbeb; width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto; border: 1px solid #fde68a;">
                            <svg style="width: 26px; height: 26px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </div>
                        <h3 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin: 0 0 0.5rem 0;">Tarifs et Réservations Pro</h3>
                        <p style="color: #64748b; font-size: 0.9rem; line-height: 1.6; margin: 0 0 1.75rem 0;">Les grilles tarifaires japonaises (saisonnières/enfants) ainsi que les modules de devis direct sont réservés aux agences de voyages partenaires.</p>
                        
                        <a href="<?php echo e(route('filament.agency.auth.login')); ?>" style="display: block; width: 100%; background: #096a61; color: white; text-decoration: none; padding: 0.9rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.9rem; text-align: center; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(9, 106, 97, 0.2); transition: background 0.2s;" onmouseover="this.style.background='#07534c'" onmouseout="this.style.background='#096a61'">
                            S'identifier sur le portail
                        </a>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal166a02a7c5ef5a9331faf66fa665c256)): ?>
<?php $attributes = $__attributesOriginal166a02a7c5ef5a9331faf66fa665c256; ?>
<?php unset($__attributesOriginal166a02a7c5ef5a9331faf66fa665c256); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal166a02a7c5ef5a9331faf66fa665c256)): ?>
<?php $component = $__componentOriginal166a02a7c5ef5a9331faf66fa665c256; ?>
<?php unset($__componentOriginal166a02a7c5ef5a9331faf66fa665c256); ?>
<?php endif; ?><?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views/filament/agency/pages/view-product.blade.php ENDPATH**/ ?>