<div style="display: flex; flex-direction: column; height: 500px; width: 100%; font-family: inherit; margin-bottom: 0.5rem; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden;">
    
    <div style="padding: 1rem; background-color: #f9fafb; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151; display: flex; align-items: center; justify-content: space-between;">
        <span>💬 Discussion du dossier</span>
        <span style="font-size: 0.75rem; font-weight: normal; color: #6b7280;">Échange direct avec l'équipe Takada</span>
    </div>

    <div id="chat-container" style="flex-grow: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; background-color: #f8fafc;">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $msg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php 
                // 💡 Détermination robuste de l'utilisateur actif pour l'affichage visuel
                $currentUserId = auth()->id();
                try {
                    if ($panel = \Filament\Facades\Filament::getCurrentPanel()) {
                        $currentUserId = auth($panel->getAuthGuard())->id();
                    } elseif (auth('agency')->check() && !str_contains(request()->headers->get('referer', ''), '/admin')) {
                        $currentUserId = auth('agency')->id();
                    }
                } catch (\Exception $e) {}

                $isMe = $msg->user_id === $currentUserId;
                $senderRole = $msg->user->role ?? 'agency';
                
                // On détermine si le message vient de l'équipe interne (Takada) ou du client (Agence)
                $isTakadaStaff = in_array($senderRole, ['super_admin', 'admin', 'agent']);
            ?>
            
            <div style="display: flex; justify-content: <?php echo e($isTakadaStaff ? 'flex-start' : 'flex-end'); ?>; width: 100%;">
                
                <div style="max-width: 80%; display: flex; flex-direction: column; align-items: <?php echo e($isTakadaStaff ? 'flex-start' : 'flex-end'); ?>;">
                    
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; padding: 0 0.25rem;">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isTakadaStaff): ?>
                            <span style="background: #096a61; color: white; font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.05em;">Équipe Takada</span>
                            <span style="font-size: 0.75rem; font-weight: 600; color: #374151;"><?php echo e($msg->user->name ?? 'Staff'); ?></span>
                        <?php else: ?>
                            <span style="font-size: 0.75rem; font-weight: 600; color: #374151;"><?php echo e($isMe ? 'Vous' : ($msg->user->name ?? 'Agence')); ?></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span style="font-size: 0.65rem; color: #9ca3af;">• <?php echo e($msg->created_at->format('d/m/Y H:i')); ?></span>
                    </div>

                    <div style="padding: 0.85rem 1.15rem; border-radius: 1rem; font-size: 0.9rem; line-height: 1.5; box-shadow: 0 1px 2px rgba(0,0,0,0.05); <?php echo e($isTakadaStaff ? 'background-color: #ffffff; color: #1f2937; border-top-left-radius: 0; border: 1px solid #e2e8f0;' : 'background-color: #096a61; color: #ffffff; border-top-right-radius: 0;'); ?>">
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($msg->message)): ?>
                            <div style="word-break: break-word;"><?php echo nl2br(e($msg->message)); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($msg->attachment_path)): ?>
                            <div style="margin-top: <?php echo e(!empty($msg->message) ? '0.75rem' : '0'); ?>; border-top: <?php echo e(!empty($msg->message) ? ($isTakadaStaff ? '1px solid #f1f5f9' : '1px solid rgba(255,255,255,0.2)') : 'none'); ?>; padding-top: <?php echo e(!empty($msg->message) ? '0.75rem' : '0'); ?>;">
                                <a href="<?php echo e(Storage::url($msg->attachment_path)); ?>" download target="_blank" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; padding: 0.5rem 0.75rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; <?php echo e($isTakadaStaff ? 'background-color: #f8fafc; color: #096a61; border: 1px solid #e2e8f0;' : 'background-color: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3);'); ?>" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                                    <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Ouvrir la pièce jointe
                                </a>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </div>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9ca3af; text-align: center;">
                <svg style="width: 48px; height: 48px; margin-bottom: 1rem; color: #cbd5e1;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <span style="font-size: 0.95rem; font-weight: 500; color: #64748b;">Aucun message pour le moment.</span>
                <span style="font-size: 0.8rem; margin-top: 0.25rem;">Posez-nous une question sur ce dossier !</span>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div style="padding: 1rem; border-top: 1px solid #e5e7eb; background-color: #ffffff;">
        <div style="display: flex; gap: 0.75rem; width: 100%; align-items: center; box-sizing: border-box;">
            
            <label style="cursor: pointer; padding: 0.5rem; color: #94a3b8; display: flex; align-items: center; justify-content: center; transition: color 0.2s;" onmouseover="this.style.color='#096a61'" onmouseout="this.style.color='#94a3b8'" title="Joindre un fichier">
                <svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
                <input type="file" wire:model="attachment" id="chat-attachment" style="opacity: 0; position: absolute; z-index: -1; width: 0; height: 0;" />
            </label>

            <input 
                type="text" 
                wire:model="newMessage" 
                wire:keydown.enter.prevent="sendMessage"
                placeholder="Écrivez votre message à l'équipe Takada..." 
                style="flex: 1 1 auto; min-width: 0; padding: 0.75rem 1.25rem; border: 1px solid #e2e8f0; border-radius: 99px; font-size: 0.9rem; outline: none; box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.02); box-sizing: border-box; background-color: #f8fafc; color: #1e293b; transition: all 0.2s;"
                onfocus="this.style.borderColor='#096a61'; this.style.backgroundColor='#ffffff';"
                onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc';"
            >
            
            <button 
                type="button" 
                wire:click="sendMessage"
                style="flex: 0 0 auto; background-color: #096a61; color: white; padding: 0.75rem 1.5rem; border-radius: 99px; font-size: 0.9rem; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s; box-sizing: border-box; box-shadow: 0 2px 4px rgba(9, 106, 97, 0.2); display: flex; align-items: center; gap: 0.5rem;"
                onmouseover="this.style.backgroundColor='#07534c'; this.style.transform='translateY(-1px)';"
                onmouseout="this.style.backgroundColor='#096a61'; this.style.transform='translateY(0)';"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="sendMessage, attachment">Envoyer</span>
                <span wire:loading wire:target="sendMessage, attachment">Envoi...</span>
            </button>
        </div>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attachment): ?>
            <div style="font-size: 0.8rem; color: #096a61; margin-top: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; padding-left: 3rem;">
                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Fichier prêt : <?php echo e($attachment->getClientOriginalName()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['attachment'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="font-size: 0.8rem; color: #ef4444; margin-top: 0.5rem; display:block; padding-left: 3rem;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newMessage'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span style="font-size: 0.8rem; color: #ef4444; margin-top: 0.5rem; display:block; padding-left: 3rem;"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        const container = document.getElementById('chat-container');
        if(container) container.scrollTop = container.scrollHeight;
        Livewire.hook('morph.updated', () => {
            if(container) container.scrollTop = container.scrollHeight;
        });
    });
</script><?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views\livewire\folder-chat.blade.php ENDPATH**/ ?>