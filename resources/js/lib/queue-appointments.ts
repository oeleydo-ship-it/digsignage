type AppointmentNavigation<TPayload, TOptions> = {
    patch: (url: string, payload: TPayload, options: TOptions) => unknown;
    post: (url: string, payload: TPayload, options: TOptions) => unknown;
};

export function submitAppointmentRequest<TPayload, TOptions>(
    navigation: AppointmentNavigation<TPayload, TOptions>,
    editing: boolean,
    url: string,
    payload: TPayload,
    options: TOptions,
) {
    if (editing) {
        navigation.patch(url, payload, options);
    } else {
        navigation.post(url, payload, options);
    }
}
