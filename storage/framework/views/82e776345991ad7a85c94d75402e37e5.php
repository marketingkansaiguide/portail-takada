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

    <div style="display: flex; flex-direction: column; gap: 32px;">

        
        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 20px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); font-family: system-ui, -apple-system, sans-serif;">
            
            
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f3f4f6; padding-bottom: 14px; margin-bottom: 18px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 22px; line-height: 1;">💳</span>
                    <div>
                        <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: #111827;">
                            Cartes IC en attente de validation
                        </h3>
                        <p style="margin: 2px 0 0 0; font-size: 12px; color: #6b7280;">
                            Synthèse complète de toutes les déclinaisons pour les dossiers confirmés
                        </p>
                    </div>
                </div>

                <div style="background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 6px 16px; border-radius: 20px; font-weight: 800; font-size: 13px;">
                    Total général : <?php echo e($this->totalPendingCards); ?> carte(s)
                </div>
            </div>

            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px;">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->icCardsSummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $hasCount = $item['count'] > 0;
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-radius: 8px; border: 1px solid <?php echo e($hasCount ? '#fcd34d' : '#f3f4f6'); ?>; background-color: <?php echo e($hasCount ? '#fffbebfb' : '#fafafa'); ?>; transition: all 0.15s ease;">
                        <span style="font-size: 13px; font-weight: <?php echo e($hasCount ? '800' : '500'); ?>; color: <?php echo e($hasCount ? '#111827' : '#9ca3af'); ?>; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 8px;" title="<?php echo e($item['name']); ?>">
                            <?php echo e($item['name']); ?>

                        </span>
                        <span style="font-size: 12px; font-weight: 900; padding: 3px 9px; border-radius: 6px; background-color: <?php echo e($hasCount ? '#f59e0b' : '#e5e7eb'); ?>; color: <?php echo e($hasCount ? '#ffffff' : '#6b7280'); ?>; flex-shrink: 0;">
                            <?php echo e($item['count']); ?>

                        </span>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

        </div>

        
        <div>
            <?php echo e($this->table); ?>

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
<?php endif; ?><?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views/filament/pages/ic-cards.blade.php ENDPATH**/ ?>