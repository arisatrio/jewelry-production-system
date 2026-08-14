export function fieldState(message?: string): 'None' | 'Negative' {
    return message ? 'Negative' : 'None';
}
