<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div style="display: flex; flex-direction: column; height: 500px; width: 100%; font-family: inherit; margin-bottom: 0.5rem; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden;">
    
    <div style="padding: 1rem; background-color: #f9fafb; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #374151;">
        💬 Discussion du dossier
    </div>

    <div id="chat-container" style="flex-grow: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 1rem;">
        @forelse($messages as $msg)
            @php $isMe = $msg->user_id === auth()->id(); @endphp
            <div style="display: flex; justify-content: {{ $isMe ? 'flex-end' : 'flex-start' }};">
                <div style="max-width: 85%; display: flex; flex-direction: column; align-items: {{ $isMe ? 'flex-end' : 'flex-start' }};">
                    <span style="font-size: 0.70rem; color: #6b7280; margin-bottom: 0.25rem; font-weight: 500;">
                        {{ $isMe ? 'Vous' : ($msg->user->name ?? 'Admin') }} • {{ $msg->created_at->format('d/m/Y H:i') }}
                    </span>
                    <div style="padding: 0.75rem 1rem; border-radius: 1rem; font-size: 0.875rem; line-height: 1.4; box-shadow: 0 1px 2px rgba(0,0,0,0.05); {{ $isMe ? 'background-color: #0f766e; color: white; border-top-right-radius: 0;' : 'background-color: #f3f4f6; color: #111827; border-top-left-radius: 0; border: 1px solid #e5e7eb;' }}">
                        @if($msg->message)
                            <div>{!! nl2br(e($msg->message)) !!}</div>
                        @endif
                        
                        @if($msg->attachment_path)
                            <div style="margin-top: 0.5rem;">
                                <a href="{{ Storage::url($msg->attachment_path) }}" target="_blank" style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; padding: 0.4rem 0.75rem; border-radius: 0.5rem; text-decoration: none; {{ $isMe ? 'background-color: rgba(255,255,255,0.2); color: white;' : 'background-color: #e5e7eb; color: #374151;' }}">
                                    📎 Voir la pièce jointe
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9ca3af; text-align: center;">
                <svg style="width: 40px; height: 40px; margin-bottom: 0.75rem; color: #9ca3af;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <span style="font-size: 0.875rem; font-style: italic;">Aucun message pour le moment.</span>
            </div>
        @endforelse
    </div>

    <div style="padding: 1rem; border-top: 1px solid #e5e7eb; background-color: #f9fafb;">
        <form wire:submit.prevent="sendMessage" style="display: flex; gap: 0.5rem; width: 100%; align-items: center; box-sizing: border-box;">
            
            <label style="cursor: pointer; padding: 0.5rem; color: #6b7280; display: flex; align-items: center; justify-content: center; transition: color 0.2s;" onmouseover="this.style.color='#0f766e'" onmouseout="this.style.color='#6b7280'">
                <svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
                <input type="file" wire:model="attachment" style="display: none;" />
            </label>

            <input 
                type="text" 
                wire:model="newMessage" 
                placeholder="Saisissez votre message..." 
                style="flex: 1 1 auto; min-width: 0; padding: 0.65rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05); box-sizing: border-box; background-color: #ffffff; color: #111827;"
            >
            <button 
                type="submit" 
                style="flex: 0 0 auto; background-color: #0f766e; color: white; padding: 0.65rem 1.25rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; border: none; cursor: pointer; transition: opacity 0.2s; box-sizing: border-box; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);"
                onmouseover="this.style.opacity='0.9'"
                onmouseout="this.style.opacity='1'"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="sendMessage">Envoyer</span>
                <span wire:loading wire:target="sendMessage">...</span>
            </button>
        </form>
        
        @if($attachment)
            <div style="font-size: 0.75rem; color: #0f766e; margin-top: 0.5rem; font-weight: 600;">
                📎 Fichier prêt : {{ $attachment->getClientOriginalName() }}
            </div>
        @endif
        @error('attachment') <span style="font-size: 0.75rem; color: #ef4444; margin-top: 0.5rem;">{{ $message }}</span> @enderror
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