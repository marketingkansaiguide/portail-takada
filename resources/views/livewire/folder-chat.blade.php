<div style="display: flex; flex-direction: column; height: 400px; font-family: inherit; margin-bottom: 0.5rem;">
    
    <div id="chat-container" style="flex-grow: 1; overflow-y: auto; padding-right: 0.5rem; display: flex; flex-direction: column; gap: 1rem;">
        @forelse($messages as $msg)
            @php $isMe = $msg->user_id === auth()->id(); @endphp
            <div style="display: flex; justify-content: {{ $isMe ? 'flex-end' : 'flex-start' }};">
                <div style="max-width: 85%; display: flex; flex-direction: column; align-items: {{ $isMe ? 'flex-end' : 'flex-start' }};">
                    <span style="font-size: 0.70rem; color: #6b7280; margin-bottom: 0.25rem; font-weight: 500;">
                        {{ $msg->user->name ?? 'Utilisateur' }} • {{ $msg->created_at->format('d/m/Y H:i') }}
                    </span>
                    <div style="padding: 0.75rem 1rem; border-radius: 1rem; font-size: 0.875rem; line-height: 1.4; box-shadow: 0 1px 2px rgba(0,0,0,0.05); {{ $isMe ? 'background-color: #0f766e; color: white; border-top-right-radius: 0;' : 'background-color: #f3f4f6; color: #111827; border-top-left-radius: 0; border: 1px solid #e5e7eb;' }}">
                        {!! nl2br(e($msg->message)) !!}
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

    <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
        <div style="display: flex; gap: 0.5rem; width: 100%; align-items: center; box-sizing: border-box;">
            <input 
                type="text" 
                wire:model="newMessage" 
                wire:keydown.enter.prevent="sendMessage" placeholder="Saisissez votre message..." 
                style="flex: 1 1 auto; min-width: 0; padding: 0.65rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05); box-sizing: border-box; background-color: #ffffff; color: #111827;"
                required
            >
            <button 
                type="button" 
                wire:click="sendMessage" style="flex: 0 0 auto; background-color: #0f766e; color: white; padding: 0.65rem 1.25rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; border: none; cursor: pointer; transition: opacity 0.2s; box-sizing: border-box; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);"
                onmouseover="this.style.opacity='0.9'"
                onmouseout="this.style.opacity='1'"
            >
                Envoyer
            </button>
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