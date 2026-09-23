import { useEffect, useMemo, useState } from 'react';
import { isWidgetType } from '@/lib/widget-keys';
import type { DesignDocument, WidgetPayload } from '@/types';

function csrfHeaders(): HeadersInit {
    const token = window.document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
    };
}

export function useHydratedDocument(
    document: DesignDocument,
    resolveUrl: string | null,
    timezone = 'UTC',
): DesignDocument {
    const [widgets, setWidgets] = useState<Record<string, WidgetPayload>>({});
    const signature = useMemo(
        () =>
            JSON.stringify(
                document.elements
                    .filter((element) => isWidgetType(element.type))
                    .map((element) => [element.id, element.type, element.props]),
            ),
        [document.elements],
    );

    useEffect(() => {
        if (!resolveUrl) {
            return;
        }

        const elements = document.elements.filter((element) =>
            isWidgetType(element.type),
        );

        if (elements.length === 0) {
            setWidgets({});

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void fetch(resolveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                signal: controller.signal,
                body: JSON.stringify({
                    timezone,
                    elements: elements.map((element) => ({
                        id: element.id,
                        type: element.type,
                        props: element.props,
                    })),
                }),
            })
                .then(async (response) => {
                    if (!response.ok) {
                        return;
                    }

                    const payload = (await response.json()) as {
                        widgets?: Record<string, WidgetPayload>;
                    };

                    if (payload.widgets) {
                        setWidgets(payload.widgets);
                    }
                })
                .catch(() => undefined);
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [document.elements, resolveUrl, signature, timezone]);

    return useMemo(
        () => ({
            ...document,
            elements: document.elements.map((element) =>
                widgets[element.id]
                    ? { ...element, widget: widgets[element.id] }
                    : element,
            ),
        }),
        [document, widgets],
    );
}
