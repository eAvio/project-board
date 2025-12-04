<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Token Generated</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold text-gray-800">API Token Generated</h1>
            <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">Success</span>
        </div>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-yellow-800">Important</h3>
                    <p class="text-sm text-yellow-700 mt-1">Copy this token now! You won't be able to see it again after closing this page.</p>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Token Name: <strong>{{ $name }}</strong></label>
        </div>

        @foreach($tokens as $index => $token)
        <div class="mb-4">
            @if(count($tokens) > 1)
            <label class="block text-sm font-medium text-gray-700 mb-2">Token {{ $index + 1 }}</label>
            @endif
            <div class="relative">
                <input 
                    type="text" 
                    id="token-{{ $index }}" 
                    value="{{ $token }}" 
                    readonly 
                    class="w-full px-4 py-3 pr-24 bg-gray-900 text-green-400 font-mono text-sm rounded-lg border-0 focus:ring-2 focus:ring-blue-500"
                />
                <button 
                    onclick="copyToken({{ $index }})" 
                    id="copy-btn-{{ $index }}"
                    class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-1"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <span id="copy-text-{{ $index }}">Copy</span>
                </button>
            </div>
        </div>
        @endforeach

        <div class="bg-gray-50 rounded-lg p-4 mb-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Usage</h4>
            <p class="text-sm text-gray-600 mb-2">Add this header to your API requests:</p>
            <code class="block bg-gray-200 px-3 py-2 rounded text-sm font-mono text-gray-800">Authorization: Bearer YOUR_TOKEN</code>
        </div>

        <div class="flex justify-between items-center">
            <a href="{{ url()->previous() }}" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium transition-colors">
                ← Back to Nova
            </a>
            <button onclick="window.print()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg transition-colors">
                Print / Save
            </button>
        </div>
    </div>

    <script>
        function copyToken(index) {
            const input = document.getElementById('token-' + index);
            input.select();
            navigator.clipboard.writeText(input.value).then(() => {
                const btn = document.getElementById('copy-btn-' + index);
                const text = document.getElementById('copy-text-' + index);
                text.textContent = 'Copied!';
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                
                setTimeout(() => {
                    text.textContent = 'Copy';
                    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
            });
        }
    </script>
</body>
</html>
