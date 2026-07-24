<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact & Sur-mesure - Takada Travel</title>
    
    <!-- Utilisation de Tailwind CSS via CDN pour un rendu immédiat et propre -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased min-h-screen flex flex-col">

    <div class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full bg-white rounded-xl shadow-lg p-8 border border-gray-100">
            
            <div class="text-center mb-8">
                <!-- Lien de retour vers le dashboard agence -->
                <a href="/agency" class="inline-flex items-center text-sm font-medium text-emerald-600 hover:text-emerald-500 mb-4 transition-colors">
                    <svg class="mr-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Retour au portail
                </a>
                
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Demande Sur-Mesure</h1>
                <p class="text-gray-500">Un groupe important ou une demande spécifique ? Détaillez-nous votre besoin, notre équipe vous préparera un devis personnalisé.</p>
            </div>

            <!-- Affichage du message de succès -->
            @if(session('success'))
                <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-md">
                    <div class="flex items-center">
                        <svg class="h-6 w-6 text-emerald-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <p class="text-sm text-emerald-700 font-medium">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            <form action="{{ route('contact.send') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Nom -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Votre nom complet <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" required value="{{ old('name', auth()->check() ? auth()->user()->name : '') }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm px-4 py-2 border">
                        @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <!-- Agence -->
                    <div>
                        <label for="agency_name" class="block text-sm font-medium text-gray-700">Nom de votre agence</label>
                        <input type="text" name="agency_name" id="agency_name" value="{{ old('agency_name') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm px-4 py-2 border">
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Adresse e-mail <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" required value="{{ old('email', auth()->check() ? auth()->user()->email : '') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm px-4 py-2 border">
                    @error('email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <!-- Sujet -->
                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700">Sujet de la demande <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" id="subject" required value="{{ old('subject') }}" placeholder="Ex: Demande de devis pour un groupe de 25 personnes"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm px-4 py-2 border">
                    @error('subject') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <!-- Message -->
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700">Détails de votre demande <span class="text-red-500">*</span></label>
                    <textarea name="message" id="message" rows="6" required placeholder="Décrivez votre besoin (dates, nombre exact de passagers, attentes particulières...)"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm px-4 py-2 border">{{ old('message') }}</textarea>
                    @error('message') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <!-- Bouton Soumission -->
                <div>
                    <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors">
                        Envoyer la demande
                    </button>
                </div>
            </form>

        </div>
    </div>

</body>
</html>