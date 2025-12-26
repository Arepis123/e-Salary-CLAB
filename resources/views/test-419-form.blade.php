<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test 419 Error - e-Salary CLAB</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-900 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 mb-6">
                Test 419 Error Handling
            </h1>

            <div class="space-y-6">
                <!-- Instructions -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-3">
                        Testing Instructions
                    </h2>
                    <ol class="text-sm text-blue-800 dark:text-blue-200 space-y-2 list-decimal list-inside">
                        <li>Open browser DevTools (F12) and go to Application/Storage tab</li>
                        <li>Click "Test with Valid CSRF" button - should show success message</li>
                        <li>In DevTools, delete all cookies for this site OR clear session storage</li>
                        <li>Click "Test with Expired Session" button - should trigger 419 handling</li>
                        <li>Watch for notification and automatic page reload</li>
                    </ol>
                </div>

                <!-- Test Buttons -->
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">
                            Method 1: Test with Valid CSRF Token
                        </h3>
                        <button
                            onclick="testWithValidToken()"
                            class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">
                            Test with Valid CSRF Token
                        </button>
                        <p class="text-sm text-zinc-500 mt-2">This should succeed and show a success message.</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">
                            Method 2: Test with Expired Session (Clear cookies first!)
                        </h3>
                        <button
                            onclick="testWithExpiredSession()"
                            class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                            Test with Expired Session
                        </button>
                        <p class="text-sm text-zinc-500 mt-2">First clear cookies in DevTools, then click this.</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">
                            Method 3: Force Invalid CSRF Token
                        </h3>
                        <button
                            onclick="testWithInvalidToken()"
                            class="px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium transition">
                            Test with Invalid Token
                        </button>
                        <p class="text-sm text-zinc-500 mt-2">This will send an invalid token and trigger 419 error.</p>
                    </div>
                </div>

                <!-- Response Area -->
                <div id="response-area" class="hidden">
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Response:</h3>
                    <div id="response-content" class="bg-zinc-100 dark:bg-zinc-700 rounded-lg p-4 text-sm font-mono"></div>
                </div>

                <!-- Other Test Links -->
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-6">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-3">
                        Other Tests
                    </h3>
                    <div class="space-y-2">
                        <a href="{{ route('test.419.page') }}"
                           class="inline-block px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm transition">
                            View 419 Error Page Directly
                        </a>
                        <p class="text-sm text-zinc-500">This will show the custom 419 error page.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showResponse(message, isError = false) {
            const area = document.getElementById('response-area');
            const content = document.getElementById('response-content');
            area.classList.remove('hidden');
            content.textContent = message;
            content.className = `rounded-lg p-4 text-sm font-mono ${isError ? 'bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200' : 'bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200'}`;
        }

        async function testWithValidToken() {
            showResponse('Sending request with valid CSRF token...', false);

            try {
                const response = await fetch('{{ route('test.419.submit') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ test: true })
                });

                const data = await response.json();

                if (response.ok) {
                    showResponse(`✓ Success! ${data.message}`, false);
                } else {
                    showResponse(`Error ${response.status}: ${JSON.stringify(data)}`, true);
                }
            } catch (error) {
                showResponse(`Network Error: ${error.message}`, true);
            }
        }

        async function testWithExpiredSession() {
            showResponse('Testing with potentially expired session...', false);

            try {
                const response = await fetch('{{ route('test.419.submit') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ test: true })
                });

                if (response.status === 419) {
                    showResponse('✓ 419 Error detected! Check for notification toast.', true);
                } else {
                    const data = await response.json();
                    showResponse(`Response ${response.status}: ${JSON.stringify(data)}`, !response.ok);
                }
            } catch (error) {
                showResponse(`Network Error: ${error.message}`, true);
            }
        }

        async function testWithInvalidToken() {
            showResponse('Sending request with invalid CSRF token...', false);

            try {
                const response = await fetch('{{ route('test.419.submit') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': 'invalid-token-12345',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ test: true })
                });

                if (response.status === 419) {
                    showResponse('✓ 419 Error triggered! The JavaScript handler should show a notification and reload the page in 3 seconds.', true);
                } else {
                    showResponse(`Unexpected response ${response.status}`, true);
                }
            } catch (error) {
                showResponse(`Network Error: ${error.message}`, true);
            }
        }
    </script>
</body>
</html>
