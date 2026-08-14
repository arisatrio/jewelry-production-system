import type { FormEvent, ReactNode } from 'react';
import { Form } from '@ui5/webcomponents-react/Form';
import { FormItem } from '@ui5/webcomponents-react/FormItem';
import { Label } from '@ui5/webcomponents-react/Label';
import { Text } from '@ui5/webcomponents-react/Text';

export type FioriSimpleFormProps = {
    id: string;
    onSubmit: (event: FormEvent) => void;
    children: ReactNode;
    labelSpan?: string;
};

export function FioriSimpleForm({
    id,
    onSubmit,
    children,
    labelSpan = 'S12 M3 L3 XL3',
}: FioriSimpleFormProps) {
    return (
        <form id={id} onSubmit={onSubmit} className="fioriSimpleForm">
            <Form
                accessibleMode="Edit"
                layout="S1 M1 L1 XL1"
                labelSpan={labelSpan}
                itemSpacing="Normal"
                className="fioriSimpleFormInner"
            >
                {children}
            </Form>
        </form>
    );
}

export type FioriFormFieldProps = {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
};

export function FioriFormField({
    label,
    required = false,
    error,
    children,
}: FioriFormFieldProps) {
    return (
        <FormItem
            labelContent={
                <Label showColon required={required} wrappingType="Normal">
                    {label}
                </Label>
            }
        >
            {children}
            {error ? <Text className="spkFioriError">{error}</Text> : null}
        </FormItem>
    );
}

export function FioriFieldAppend({
    append,
    children,
    className,
}: {
    append: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={['fioriSimpleFormFieldWithAppend', className]
                .filter(Boolean)
                .join(' ')}
        >
            {children}
            <span className="fioriSimpleFormAppend">{append}</span>
        </div>
    );
}

export function FioriTwinFields({ children }: { children: ReactNode }) {
    return <div className="fioriSimpleFormTwin">{children}</div>;
}

export function FioriFormError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <Text className="spkFioriError">{message}</Text>;
}
