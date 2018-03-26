export const sleep = async (timeout: number) => new Promise(resolve => {
    setTimeout(resolve, timeout);
});

export const fetchJson = async (url: string, retryCount = 5): Promise<any> => {
    try {
        return fetch(url, { credentials: 'same-origin' }).then(response => response.json());
    } catch (e) {
        if (!retryCount) {
            throw e;
        }
        await sleep(1000);
        return fetchJson(url, retryCount - 1);
    }
}