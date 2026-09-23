const input = document.getElementById('server');
const error = document.getElementById('error');
const params = new URLSearchParams(window.location.search);

if (params.get('server')) {
    input.value = params.get('server');
}

document.getElementById('start').addEventListener('click', async () => {
    error.textContent = '';
    const serverUrl = input.value.trim();

    if (!/^https?:\/\//i.test(serverUrl)) {
        error.textContent = 'Use an http:// or https:// server URL.';
        return;
    }

    try {
        await window.digsignagePlayer.start(serverUrl);
    } catch (caught) {
        error.textContent =
            caught instanceof Error ? caught.message : 'Unable to start player.';
    }
});
