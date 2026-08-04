<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Alerte Ouverture Ventes - Cartes IC</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f5f7; margin: 0; padding: 20px;">
    <div style="max-width: 680px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; padding: 30px; border: 1px solid #e5e7eb;">
        
        <div style="text-align: center; margin-bottom: 25px;">
            <span style="font-size: 40px;">💳</span>
            <h1 style="color: #b45309; font-size: 20px; margin-top: 10px;">Ouverture des ventes atteinte - Cartes IC</h1>
            <p style="color: #6b7280; font-size: 13px; margin-top: 5px;">
                Récapitulatif des prestations dont la date d'ouverture des ventes (J-) est atteinte.
            </p>
        </div>

        <p style="color: #374151; font-size: 14px; line-height: 1.6;">
            Bonjour l'équipe Takada,
        </p>

        <p style="color: #374151; font-size: 14px; line-height: 1.6;">
            La date d'ouverture des ventes a été atteinte pour <strong>{{ count($items) }} prestation(s)</strong> de Cartes IC en attente de validation :
        </p>

        <div style="overflow-x: auto; margin: 20px 0;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; color: #1f2937;">
                <thead>
                    <tr style="background-color: #fef3c7; color: #92400e; text-align: left; border-bottom: 2px solid #fcd34d;">
                        <th style="padding: 10px; border-right: 1px solid #fcd34d;">Réf. / Dossier</th>
                        <th style="padding: 10px; border-right: 1px solid #fcd34d;">Pax Leader</th>
                        <th style="padding: 10px; text-align: center; border-right: 1px solid #fcd34d;">Qté</th>
                        <th style="padding: 10px; border-right: 1px solid #fcd34d;">Date Prestation</th>
                        <th style="padding: 10px;">Livraison / Envoi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php
                            $effectiveDate = $item->service_date ? $item->service_date->format('d/m/Y') : ($item->folder?->start_date ? $item->folder->start_date->format('d/m/Y') : '---');
                        @endphp
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 10px; border-right: 1px solid #f3f4f6;">
                                <strong>{{ $item->folder?->reference }}</strong><br>
                                <span style="font-size: 11px; color: #6b7280;">{{ $item->folder?->folder_name }}</span>
                            </td>
                            <td style="padding: 10px; border-right: 1px solid #f3f4f6;">
                                {{ $item->folder?->lead_traveler_name }}
                            </td>
                            <td style="padding: 10px; text-align: center; font-weight: bold; color: #d97706; border-right: 1px solid #f3f4f6;">
                                {{ $item->quantity }} carte(s)
                            </td>
                            <td style="padding: 10px; border-right: 1px solid #f3f4f6;">
                                {{ $effectiveDate }}
                            </td>
                            <td style="padding: 10px;">
                                <span style="font-size: 12px; font-weight: bold;">{{ $item->folder?->dispatch_method_label }}</span><br>
                                <span style="font-size: 11px; color: #6b7280;">{{ $item->folder?->dispatch_address }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ route('filament.admin.pages.ic-cards') }}" 
               style="background-color: #f59e0b; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">
               Accéder à la gestion des Cartes IC ↗
            </a>
        </div>

        <hr style="border: none; border-top: 1px solid #f3f4f6; margin: 30px 0 15px 0;">
        <p style="font-size: 11px; color: #9ca3af; text-align: center;">
            Notification automatique générée par le Portail Takada Travel.
        </p>
    </div>
</body>
</html>