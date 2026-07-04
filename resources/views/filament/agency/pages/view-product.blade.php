<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 2.5rem; font-family: system-ui, sans-serif;">
        
        <div style="grid-column: span 12 / span 12;" class="xl:col-span-8">
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                {{-- Fil d'ariane & Titre haut de gamme --}}
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: #d97706; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <span>{{ $product->category->name ?? 'Prestation Japonaise' }}</span>
                        @if(isset($product->is_public) && !$product->is_public)
                            <span style="background: #fee2e2; color: #991b1b; font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: 0.25rem; margin-left: 0.5rem;">Tarif Négocié / Privé</span>
                        @endif
                    </div>
                    <h1 style="font-size: 2.5rem; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; margin: 0;">{{ $product->name }}</h1>
                    
                    {{-- ⏳ Alerte d'ouverture automatique des réservations --}}
                    <div style="background: #fffbeb; border-left: 4px solid #d97706; padding: 1rem; border-radius: 0 0.75rem 0.75rem 0; display: flex; align-items: center; gap: 0.75rem; color: #92400e; font-size: 0.9rem; margin-top: 0.5rem;">
                        <svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span><strong>Disponibilité :</strong> Pour ce produit, votre demande sera traitée à l'ouverture officielle des réservations, soit <strong>2 mois avant</strong> votre date souhaitée.</span>
                    </div>
                </div>

                {{-- Visuel principal --}}
                <div style="border-radius: 1.25rem; overflow: hidden; height: 420px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); background: #f1f5f9;">
                    @php $mainImage = $product->image ?? ($product->images[0] ?? null); @endphp
                    @if($mainImage)
                        <img src="{{ asset('storage/' . $mainImage) }}" style="width: 100%; height: 100%; object-fit: cover;" alt="{{ $product->name }}">
                    @else
                        <div style="display: flex; height: 100%; align-items: center; justify-content: center; color: #cbd5e1; font-weight: 500; background: #f8fafc;">Aucun visuel disponible</div>
                    @endif
                </div>

                {{-- Description de l'activité --}}
                <div style="background: white; padding: 2.5rem; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                    <h2 style="font-size: 1.4rem; font-weight: 700; color: #096a61; margin-top:0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="width: 12px; height: 12px; background: #d97706; border-radius: 50%;"></span>
                        Présentation de la prestation
                    </h2>
                    <div style="color: #475569; line-height: 1.8; font-size: 1.05rem;">
                        {!! $product->description !!}
                    </div>
                </div>

                {{-- 💡 RÉINTÉGRATION : Formulaire logistique requis par le prestataire (Champs personnalisés) --}}
                @if(!empty($product->custom_field_definitions) && count($product->custom_field_definitions) > 0)
                    <div style="background: white; padding: 2.5rem; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                        <h2 style="font-size: 1.4rem; font-weight: 700; color: #096a61; margin-top:0; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 12px; height: 12px; background: #d97706; border-radius: 50%;"></span>
                            Informations logistiques requises
                        </h2>
                        <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 1.5rem; margin-top: 0;">Le fournisseur exige les renseignements suivants pour valider cette réservation :</p>
                        
                        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.5rem;">
                            @foreach($product->custom_field_definitions as $def)
                                @php 
                                    $key = !empty($def['key']) ? $def['key'] : \Illuminate\Support\Str::slug($def['name'] ?? 'custom', '_');
                                    $isRequired = $def['is_required'] ?? false;
                                @php
                                <div style="grid-column: span {{ $def['type'] === 'textarea' ? '2' : '1' }}; display: flex; flex-direction: column; gap: 0.4rem;">
                                    <label style="font-size: 0.9rem; font-weight: 600; color: #374151;">
                                        {{ $def['name'] }} {!! $isRequired ? '<span style="color:#dc2626;">*</span>' : '' !!}
                                        @if($def['is_per_passenger'] ?? false) <small style="color:#64748b; font-weight:normal;">(Par passager)</small> @endif
                                    </label>

                                    @if(!auth('agency')->check())
                                        {{-- Mode vitrine : Input bloqué --}}
                                        <input type="text" placeholder="Connectez-vous pour renseigner ce champ" disabled style="width:100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; background:#f8fafc; color:#94a3b8; font-size:0.9rem;">
                                    @else
                                        {{-- Mode connecté : Formulaire fonctionnel --}}
                                        @if($def['type'] === 'textarea')
                                            <textarea wire:model="customValues.{{ $key }}" rows="3" style="width:100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size:0.9rem; outline:none; font-family:inherit;"></textarea>
                                        @elseif($def['type'] === 'select')
                                            <select wire:model="customValues.{{ $key }}" style="width:100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size:0.9rem; outline:none; background:white;">
                                                <option value="">Sélectionnez une option...</option>
                                                @foreach($def['choices'] ?? [] as $choice)
                                                    <option value="{{ $choice }}">{{ $choice }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($def['type'] === 'date')
                                            <input type="date" wire:model="customValues.{{ $key }}" style="width:100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size:0.9rem; outline:none;">
                                        @elseif($def['type'] === 'number')
                                            <input type="number" wire:model="customValues.{{ $key }}" style="width:100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size:0.9rem; outline:none;">
                                        @elseif($def['type'] === 'toggle')
                                            <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.25rem;">
                                                <input type="checkbox" wire:model="customValues.{{ $key }}" id="check_{{ $key }}" style="width:18px; height:18px; accent-color:#096a61;">
                                                <label Jar pour="check_{{ $key }}" style="font-size:0.875rem; color:#4b5563;">Oui, je confirme cette option</label>
                                            </div>
                                        @else
                                            <input type="text" wire:model="customValues.{{ $key }}" style="width:100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size:0.9rem; outline:none;">
                                        @endif
                                        
                                        @error("customValues.{$key}") <span style="color:#dc2626; font-size:0.75rem; font-weight:500;">{{ $message }}</span> @enderror
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div style="grid-column: span 12 / span 12;" class="xl:col-span-4">
            <div style="position: sticky; top: 2rem; display: flex; flex-direction: column; gap: 1.5rem;">
                
                @if(auth('agency')->check())
                    <div style="background: white; border-radius: 1.25rem; padding: 2rem; border: 1px solid #f1f5f9; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.03);">
                        <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem;">Tarif Agence</span>
                        
                        @if($product->price == 0 || $product->price == null)
                            {{-- 💡 Gestion des produits sur Devis / "En attente de prix" --}}
                            <div style="background: #fff7ed; border: 1px solid #ffedd5; color: #c2410c; padding: 1rem; border-radius: 0.75rem; text-align: center; margin-bottom: 1.5rem; font-weight: 700; font-size: 1.1rem;">
                                ⚠️ Tarif sur Devis personnalisé
                            </div>
                        @else
                            <div style="font-size: 2.5rem; font-weight: 800; color: #096a61; margin-bottom: 0.25rem;">{{ number_format($product->price, 0, '.', ' ') }} ¥</div>
                            <span style="font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 1.5rem;">Prix net indicatif hors suppléments et saison</span>
                        @endif

                        @if($product->options->count() > 0)
                            <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; margin-bottom: 1.5rem;">
                                <h4 style="font-size: 0.9rem; font-weight: 700; color: #1e293b; margin: 0 0 0.75rem 0;">Options complémentaires :</h4>
                                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    @foreach($product->options as $option)
                                        <div style="background: #f8fafc; border: 1px solid #f1f5f9; padding: 0.75rem; border-radius: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                            <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                                                <input type="checkbox" wire:model="selectedOptions.{{ $option->id }}.enabled" id="opt_{{ $option->id }}" style="margin-top: 0.2rem; accent-color: #096a61;">
                                                <label for="opt_{{ $option->id }}" style="font-size: 0.85rem; font-weight: 600; color: #374151; cursor: pointer; flex-grow: 1;">
                                                    {{ $option->name }}
                                                    <span style="display: block; font-size: 0.8rem; color: #096a61; font-weight: 700; margin-top: 0.1rem;">+ {{ number_format($option->price, 0, '.', ' ') }} ¥</span>
                                                </label>
                                            </div>

                                            {{-- Si l'option est activée et requiert une quantité manuelle --}}
                                            @if(($selectedOptions[$option->id]['enabled'] ?? false) && $option->billing_type === 'manual')
                                                <div style="display: flex; align-items: center; gap: 0.5rem; background: white; padding: 0.35rem 0.5rem; border-radius: 0.375rem; border: 1px solid #e2e8f0; margin-left: 1.25rem;">
                                                    <span style="font-size: 0.75rem; color: #64748b;">Quantité option :</span>
                                                    <input type="number" wire:model="selectedOptions.{{ $option->id }}.quantity" min="1" style="width: 50px; border: none; padding: 0; outline: none; text-align: center; font-size: 0.85rem; font-weight: 600;">
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div style="display: flex; flex-direction: column; gap: 1rem; border-top: 1px solid #f1f5f9; padding-top: 1.25rem;">
                            <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                <label style="font-size: 0.85rem; font-weight: 700; color: #374151;">Dossier client de destination</label>
                                <select wire:model="selectedFolderId" style="width: 100%; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; color: #111827;">
                                    <option value="">-- Choisir un dossier voyage --</option>
                                    @foreach($this->availableFolders as $folder)
                                        <option value="{{ $folder->id }}">{{ $folder->folder_name }} ({{ $folder->reference }})</option>
                                    @endforeach
                                </select>
                                @error('selectedFolderId') <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;">{{ $message }}</span> @enderror
                            </div>

                            <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 1rem;">
                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    <label style="font-size: 0.85rem; font-weight: 700; color: #374151;">Date de l'activité</label>
                                    <input type="date" wire:model="serviceDate" style="width: 100%; padding: 0.55rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white;">
                                    @error('serviceDate') <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;">{{ $message }}</span> @enderror
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    <label style="font-size: 0.85rem; font-weight: 700; color: #374151;">Participants</label>
                                    <input type="number" wire:model="quantity" min="1" style="width: 100%; padding: 0.55rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; background: white; text-align: center;">
                                    @error('quantity') <span style="color: #dc2626; font-size: 0.75rem; font-weight: 500;">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <button type="button" wire:click="addToFolder" style="width: 100%; background: #096a61; color: white; border: none; padding: 0.9rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.9rem; cursor: pointer; text-align: center; box-shadow: 0 4px 6px -1px rgba(9, 106, 97, 0.2); margin-top: 0.5rem; transition: background 0.2s;" onmouseover="this.style.background='#07534c'" onmouseout="this.style.background='#096a61'">
                                AJOUTER AU DOSSIER DE VOYAGE
                            </button>
                        </div>
                    </div>
                @else
                    <div style="background: white; border-radius: 1.25rem; padding: 2.25rem 2rem; border: 1px solid #f1f5f9; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.04);">
                        <div style="color: #d97706; background: #fffbeb; width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto; border: 1px solid #fde68a;">
                            <svg style="width: 26px; height: 26px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </div>
                        <h3 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin: 0 0 0.5rem 0;">Tarifs et Réservations Pro</h3>
                        <p style="color: #64748b; font-size: 0.9rem; line-height: 1.6; margin: 0 0 1.75rem 0;">Les grilles tarifaires japonaises (saisonnières/enfants) ainsi que les modules de devis direct sont réservés aux agences de voyages partenaires.</p>
                        
                        <a href="{{ route('filament.agency.auth.login') }}" style="display: block; width: 100%; background: #096a61; color: white; text-decoration: none; padding: 0.9rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.9rem; text-align: center; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(9, 106, 97, 0.2); transition: background 0.2s;" onmouseover="this.style.background='#07534c'" onmouseout="this.style.background='#096a61'">
                            S'identifier sur le portail
                        </a>
                    </div>
                @endif
                
            </div>
        </div>
    </div>
</x-filament-panels::page>