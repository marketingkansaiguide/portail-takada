<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
</head>
<body style="background-color: #f3f4f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f3f4f6; padding: 30px 10px;">
        <tr>
            <td align="center">
                <table width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                    <tr>
                        <td style="background-color: #096a61; padding: 40px 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: 0.5px;">Nouveau Message</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px; color: #374151; line-height: 1.6; font-size: 16px;">
                            <p style="margin-top: 0; font-size: 17px;">Bonjour,</p>
                            <p>L'équipe <strong>Takada</strong> vient de vous laisser un nouveau message concernant votre dossier de voyage :</p>
                            
                            <div style="background-color: #f8fafc; border-left: 4px solid #096a61; padding: 20px; margin: 25px 0; border-radius: 0 8px 8px 0;">
                                <p style="margin: 0; font-size: 18px; color: #111827; font-weight: 700;">{{ $folder->folder_name }}</p>
                                <p style="margin: 6px 0 0 0; font-size: 14px; color: #6b7280; font-weight: 500;">👤 Pax principal : {{ $folder->lead_traveler_name }}</p>
                            </div>

                            <p style="margin-bottom: 30px;">Pour lire ce message et y répondre, veuillez accéder à votre portail B2B en cliquant sur le lien ci-dessous.</p>

                            <div style="text-align: center; margin: 35px 0;">
                                <a href="{{ url('/agency-folders/' . $folder->id . '/edit') }}" style="background-color: #096a61; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-size: 16px; font-weight: 600; display: inline-block;">Ouvrir le dossier</a>
                            </div>

                            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
                            
                            <p style="margin: 0; font-size: 15px;">Cordialement,<br><strong style="color: #096a61;">L'équipe Takada</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; text-align: center; border-top: 1px solid #f3f4f6;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">Cet e-mail est généré automatiquement, merci de ne pas y répondre directement.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>