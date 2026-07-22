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

    <div style="display: flex; flex-direction: column; gap: 2rem;">
        
        <div style="display: flex; flex-direction: column; gap: 1rem; border-bottom: 2px solid #f3f4f6; padding-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 2rem; font-weight: 800; color: #096a61; margin: 0;">Explorez le Japon</h1>
                    <p style="color: #64748b; font-size: 1rem; margin-top: 0.25rem;">Découvrez nos activités exclusives pour vos groupes et voyageurs.</p>
                </div>
                
                <div style="position: relative; width: 100%; max-width: 400px;">
                    <input type="text" wire:model.live="search" placeholder="Rechercher une activité..." 
                           style="width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; border: 1px solid #e2e8f0; border-radius: 9999px; font-size: 0.9rem; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <svg style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #94a3b8;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div style="background: white; border-radius: 1rem; overflow: hidden; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;" 
                     onmouseover="this.style.transform='translateY(-4px)'; this.style.box-shadow='0 10px 15px -3px rgba(0,0,0,0.1)';" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.box-shadow='0 4px 6px -1px rgba(0,0,0,0.05)';"
                     onclick="window.location.href='<?php echo e(route('filament.agency.pages.view-product', ['record' => $product->id])); ?>'">
                    
                    <div style="height: 200px; background-color: #f8fafc; position: relative; overflow: hidden;">
                        <?php 
                            $firstImage = null;
                            if (!empty($product->images) && is_array($product->images)) {
                                $firstImage = $product->images[0];
                            }
                        ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($firstImage): ?>
                            <img src="<?php echo e(asset('storage/' . $firstImage)); ?>" alt="<?php echo e($product->name); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="display: flex; height: 100%; align-items: center; justify-content: center; color: #cbd5e1; background: #f8fafc;">
                                <svg style="width: 48px; height: 48px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        
                        <div style="position: absolute; top: 1rem; left: 1rem; background: rgba(9, 106, 97, 0.9); color: white; padding: 0.2rem 0.75rem; border-radius: 0.5rem; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                            <?php echo e($product->category->name ?? 'Activité'); ?>

                        </div>
                    </div>

                    <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0; line-height: 1.3;"><?php echo e($product->name); ?></h3>
                        
                        <p style="font-size: 0.85rem; color: #64748b; line-height: 1.5; height: 3.8rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                            <?php echo e(strip_tags($product->description)); ?>

                        </p>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth('agency')->check()): ?>
                                <?php
                                    $minPrice = null;
                                    if ($product->productPeriods) {
                                        foreach($product->productPeriods as $period) {
                                            if ($period->productPrices) {
                                                foreach($period->productPrices as $price) {
                                                    if ($minPrice === null || $price->price < $minPrice) {
                                                        $minPrice = $price->price;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                ?>

                                <div style="display: flex; flex-direction: column;">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->is_on_demand): ?>
                                        <span style="font-size: 0.85rem; font-weight: 800; color: #c2410c; margin-top:0.5rem;">Sur Devis</span>
                                    <?php elseif($minPrice !== null): ?>
                                        <span style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; font-weight: 700;">À partir de</span>
                                        <span style="font-size: 1.25rem; font-weight: 800; color: #096a61;"><?php echo e(number_format($minPrice, 0, '.', ' ')); ?> ¥</span>
                                    <?php else: ?>
                                        <span style="font-size: 0.85rem; font-weight: 800; color: #096a61; margin-top:0.5rem;">Prix saisonnier</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="font-size: 0.75rem; font-weight: 600; color: #d97706; background: #fffbeb; padding: 0.25rem 0.6rem; border-radius: 0.375rem;">Tarifs pro sur connexion</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <a href="<?php echo e(route('filament.agency.pages.view-product', ['record' => $product->id])); ?>" 
                               style="background: #096a61; color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8rem; font-weight: 700; transition: background 0.2s;">
                                Découvrir
                            </a>
                        </div>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->products->isEmpty()): ?>
            <div style="text-align: center; padding: 4rem 2rem; background: #f9fafb; border-radius: 1rem; border: 2px dashed #e2e8f0;">
                <p style="color: #94a3b8; font-size: 1.1rem;">Aucun produit ne correspond à votre recherche.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php endif; ?><?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views/filament/agency/pages/catalogue.blade.php ENDPATH**/ ?>