/** Keep manifest activation sequential while retaining one requested refresh. */
export function serializePlayerRefresh(refresh: () => Promise<void>): () => Promise<void> {
    let running: Promise<void> | null = null;
    let requestedAgain = false;

    return async () => {
        if (running) {
            requestedAgain = true;
            return running;
        }

        let failed = false;
        let lastError: unknown;

        do {
            requestedAgain = false;
            running = Promise.resolve().then(refresh);

            try {
                await running;
                failed = false;
            } catch (error) {
                failed = true;
                lastError = error;
            } finally {
                running = null;
            }
        } while (requestedAgain);

        if (failed) {
            throw lastError;
        }
    };
}
