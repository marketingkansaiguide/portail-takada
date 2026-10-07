<div class="fc-chat" style="display: flex; flex-direction: column; height: 500px; width: 100%; font-family: inherit; margin-bottom: 0.5rem; border-radius: 0.75rem; overflow: hidden;">
    {{-- Couleurs du chat : reprises des palettes du panel (thème clair « papier », thème foncé « marine »). --}}
    <style>
        .fc-chat {
            --fc-bg: #ffffff; --fc-thread: var(--gray-50); --fc-header: var(--gray-50); --fc-border: var(--gray-200);
            --fc-text: var(--gray-700); --fc-muted: var(--gray-500); --fc-empty-icon: var(--gray-300);
            --fc-staff-bg: #ffffff; --fc-staff-text: var(--gray-800); --fc-staff-border: var(--gray-200);
            --fc-accent: var(--tk-accent); --fc-danger: #dc2626;
            --fc-action-bg: #fef2f2; --fc-action-border: #f87171;
            --fc-input-bg: var(--gray-50); --fc-input-focus-bg: #ffffff; --fc-input-text: var(--gray-800); --fc-input-border: var(--gray-200);
            --fc-attach-bg: var(--gray-50); --fc-attachment-sep: var(--gray-100);
            background: var(--fc-bg); border: 1px solid var(--fc-border);
        }
        .dark .fc-chat {
            --fc-bg: var(--gray-900); --fc-thread: var(--gray-950); --fc-header: var(--gray-800); --fc-border: var(--gray-700);
            --fc-text: #f8fafc; --fc-muted: var(--gray-400); --fc-empty-icon: var(--gray-600);
            --fc-staff-bg: var(--gray-800); --fc-staff-text: #f8fafc; --fc-staff-border: var(--gray-700);
            --fc-danger: #fca5a5;
            --fc-action-bg: #4c0519; --fc-action-border: #be123c;
            --fc-input-bg: var(--gray-800); --fc-input-focus-bg: var(--gray-900); --fc-input-text: #f8fafc; --fc-input-border: var(--gray-600);
            --fc-attach-bg: var(--gray-900); --fc-attachment-sep: var(--gray-700);
        }
        .fc-header { background-color: var(--fc-header); border-bottom: 1px solid var(--fc-border); color: var(--fc-text); }
        .fc-thread { background-color: var(--fc-thread); }
        .fc-name { color: var(--fc-text); }
        .fc-muted { color: var(--fc-muted); }
        .fc-empty-icon { color: var(--fc-empty-icon); }
        .fc-bubble-staff { background-color: var(--fc-staff-bg); color: var(--fc-staff-text); border: 1px solid var(--fc-staff-border); }
        .fc-bubble-client { background-color: var(--primary-600); color: #ffffff; --fc-attachment-sep: rgba(255, 255, 255, 0.2); }
        .fc-attachment { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; padding: 0.5rem 0.75rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; }
        .fc-attachment:hover { opacity: 0.8; }
        .fc-bubble-staff .fc-attachment { background-color: var(--fc-attach-bg); color: var(--fc-accent); border: 1px solid var(--fc-staff-border); }
        .fc-bubble-client .fc-attachment { background-color: rgba(255, 255, 255, 0.15); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); }
        .fc-action { background-color: var(--fc-action-bg); color: var(--fc-danger); border: 1px solid var(--fc-action-border); }
        .fc-composer { border-top: 1px solid var(--fc-border); background-color: var(--fc-bg); }
        .fc-attach { color: var(--fc-muted); }
        .fc-attach:hover { color: var(--fc-accent); }
        .fc-input { background-color: var(--fc-input-bg); color: var(--fc-input-text); border: 1px solid var(--fc-input-border); }
        .fc-input::placeholder { color: var(--fc-muted); }
        .fc-input:focus { border-color: var(--fc-accent); background-color: var(--fc-input-focus-bg); }
        .fc-send { background-color: var(--primary-600); color: #ffffff; }
        .fc-send:hover { background-color: var(--primary-700); transform: translateY(-1px); }
        .fc-danger { color: var(--fc-danger); }
        .fc-accent { color: var(--fc-accent); }
        @media (max-width: 480px) {
            .fc-header { flex-direction: column; align-items: flex-start !important; gap: 0.2rem; }
            .fc-thread { padding: 1rem !important; gap: 1rem !important; }
            .fc-thread > div > div { max-width: 92% !important; }
            .fc-composer { padding: 0.75rem !important; }
            .fc-composer > div > div:first-child { gap: 0.4rem !important; }
            .fc-attach { padding: 0.25rem !important; }
            .fc-input { padding: 0.65rem 0.9rem !important; }
            .fc-send { padding: 0.65rem 0.9rem !important; }
            .fc-composer [style*="padding-left: 3rem"] { padding-left: 0 !important; }
        }
    </style>

    
    <div class="fc-header" style="padding: 1rem; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
        <span>💬 {{ __('Discussion du dossier') }}</span>
        <span class="fc-muted" style="font-size: 0.75rem; font-weight: normal;">{{ __('Échange direct avec l’équipe Takada') }}</span>
    </div>

    <div id="chat-container" class="fc-thread" style="flex-grow: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
        @forelse($messages as $msg)
            @php 
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
            @endphp
            
            <div style="display: flex; justify-content: {{ $isTakadaStaff ? 'flex-start' : 'flex-end' }}; width: 100%;">
                
                <div style="max-width: 80%; display: flex; flex-direction: column; align-items: {{ $isTakadaStaff ? 'flex-start' : 'flex-end' }};">
                    
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; padding: 0 0.25rem;">
                        @if($isTakadaStaff)
                            <span style="background: var(--primary-600); color: white; font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">{{ __('Équipe Takada') }}</span>
                            <span class="fc-name" style="font-size: 0.75rem; font-weight: 600;">{{ $msg->user->name ?? 'Staff' }}</span>
                        @else
                            <span class="fc-name" style="font-size: 0.75rem; font-weight: 600;">{{ $isMe ? 'Vous' : ($msg->user->name ?? 'Agence') }}</span>
                        @endif
                        <span class="fc-muted" style="font-size: 0.65rem;">• {{ $msg->created_at->format('d/m/Y H:i') }}</span>
                    </div>

                    <div class="{{ $isTakadaStaff ? 'fc-bubble-staff' : 'fc-bubble-client' }}" style="padding: 0.85rem 1.15rem; border-radius: 1rem; font-size: 0.9rem; line-height: 1.5; box-shadow: 0 1px 2px rgba(0,0,0,0.05); {{ $isTakadaStaff ? 'border-top-left-radius: 0;' : 'border-top-right-radius: 0;' }}">
                        
                        @if($msg->is_action_required ?? false)
                            <div class="fc-action" style="display: inline-block; font-size: 0.7rem; font-weight: bold; padding: 2px 8px; border-radius: 99px; margin-bottom: 0.5rem;">
                                ⚠️ ACTION REQUISE
                            </div>
                        @endif

                        @if(!empty($msg->message))
                            <div style="word-break: break-word;">{!! nl2br(e($msg->message)) !!}</div>
                        @endif
                        
                        @if(!empty($msg->attachment_path))
                            <div style="margin-top: {{ !empty($msg->message) ? '0.75rem' : '0' }}; border-top: {{ !empty($msg->message) ? '1px solid var(--fc-attachment-sep)' : 'none' }}; padding-top: {{ !empty($msg->message) ? '0.75rem' : '0' }};">
                                <a href="{{ app(\App\Services\FolderDriveStorage::class)->isDrivePath($msg->attachment_path) ? route('folders.drive-file', ['folder' => $folder, 'fileId' => app(\App\Services\FolderDriveStorage::class)->fileId($msg->attachment_path)]) : Storage::disk('public')->url($msg->attachment_path) }}" download target="_blank" class="fc-attachment">
                                    <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {{ __('Ouvrir la pièce jointe') }}
                                </a>
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        @empty
            <div class="fc-muted" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center;">
                <svg class="fc-empty-icon" style="width: 48px; height: 48px; margin-bottom: 1rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <span class="fc-muted" style="font-size: 0.95rem; font-weight: 500;">{{ __('Aucun message pour le moment.') }}</span>
                <span style="font-size: 0.8rem; margin-top: 0.25rem;">{{ __('Posez-nous une question sur ce dossier !') }}</span>
            </div>
        @endforelse
    </div>

    <div class="fc-composer" style="padding: 1rem;">
        
        @php
            // 💡 Détermination très stricte : on vérifie que nous sommes bel et bien sur le portail administrateur
            $isAdminSide = false;
            try {
                if ($panel = \Filament\Facades\Filament::getCurrentPanel()) {
                    $isAdminSide = ($panel->getId() === 'admin');
                } elseif (str_contains(request()->headers->get('referer', ''), '/admin')) {
                    $isAdminSide = true;
                }
            } catch (\Exception $e) {}
        @endphp

        <div style="display: flex; flex-direction: column; gap: 0.75rem; width: 100%; box-sizing: border-box;">
            
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <label class="fc-attach" style="cursor: pointer; padding: 0.5rem; display: flex; align-items: center; justify-content: center; transition: color 0.2s;" title="Joindre un fichier">
                    <svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                    <input type="file" wire:model="attachment" id="chat-attachment" style="opacity: 0; position: absolute; z-index: -1; width: 0; height: 0;" />
                </label>

                <input 
                    type="text" 
                    wire:model="newMessage" 
                    wire:keydown.enter.prevent="sendMessage"
                placeholder="{{ __('Écrivez votre message...') }}"
                    class="fc-input"
                    style="flex: 1 1 auto; min-width: 0; padding: 0.75rem 1.25rem; border-radius: 99px; font-size: 0.9rem; outline: none; box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.02); box-sizing: border-box; transition: all 0.2s;"
                >
                
                <button 
                    type="button" 
                    wire:click="sendMessage"
                    class="fc-send" style="flex: 0 0 auto; padding: 0.75rem 1.5rem; border-radius: 99px; font-size: 0.9rem; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s; box-sizing: border-box; box-shadow: 0 2px 4px color-mix(in srgb, var(--primary-600) 20%, transparent); display: flex; align-items: center; gap: 0.5rem;"
                    wire:loading.attr="disabled"
                >
                <span wire:loading.remove wire:target="sendMessage, attachment">{{ __('Envoyer') }}</span>
                <span wire:loading wire:target="sendMessage, attachment">{{ __('Envoi...') }}</span>
                </button>
            </div>

            {{-- 💡 La case n'apparaît QUE si on est sur la page Admin ET que l'on a les droits --}}
            @if($isAdminSide && in_array(auth()->user()->role ?? '', ['super_admin', 'admin']))
                <div style="display: flex; align-items: center; gap: 0.5rem; padding-left: 3rem;">
                    <input type="checkbox" wire:model="isActionRequired" id="actionRequired" style="cursor: pointer;">
                    <label for="actionRequired" class="fc-danger" style="font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                    {{ __('Marquer ce message comme nécessitant une action (Action Requise)') }}
                    </label>
                </div>
            @endif
            
            @if($attachment)
                <div class="fc-accent" style="font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; padding-left: 3rem;">
                    <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                {{ __('Fichier prêt :') }} {{ $attachment->getClientOriginalName() }}
                </div>
            @endif
            @error('attachment') <span class="fc-danger" style="font-size: 0.8rem; display:block; padding-left: 3rem;">{{ $message }}</span> @enderror
            @error('newMessage') <span class="fc-danger" style="font-size: 0.8rem; display:block; padding-left: 3rem;">{{ $message }}</span> @enderror
        </div>
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
</script>
