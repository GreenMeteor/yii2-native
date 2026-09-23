const endpoint = '/_native/bridge';

export async function call(method, params = {}) {
    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ method, params })
    });

    if (!response.ok) {
        throw new Error(`Native bridge request failed: ${response.status}`);
    }

    return response.json();
}

export const Native = { call };
