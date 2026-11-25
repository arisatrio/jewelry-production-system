type LaravelJsonErrorPayload = {
    message?: string;
    errors?: Record<string, string | string[]>;
};

export function formErrorMessages(
    errors: Record<string, string | undefined>,
): string[] {
    return Array.from(new Set(Object.values(errors).filter(Boolean)));
}

export function firstFormErrorMessage(
    errors: Record<string, string | undefined>,
    fallback: string,
): string {
    return formErrorMessages(errors)[0] ?? fallback;
}

export function httpExceptionPayloadMessage(
    response: { data?: string },
    fallback: string,
): string {
    if (!response.data?.trim()) {
        return fallback;
    }

    try {
        const payload = JSON.parse(response.data) as LaravelJsonErrorPayload;
        const fieldError = Object.values(payload.errors ?? {})
            .flat()
            .map((message) => String(message).trim())
            .find((message) => message !== '');

        if (fieldError) {
            return fieldError;
        }

        if (payload.message?.trim()) {
            return payload.message.trim();
        }
    } catch {
        return fallback;
    }

    return fallback;
}