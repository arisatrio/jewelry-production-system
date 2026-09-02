type SpkItemSkuColumnProps = {
    typeCode: string | null;
    productItemName: string | null;
    skuCode: string | null;
    itemDescription?: string | null;
};

export function SpkItemSkuColumn({
    typeCode,
    productItemName,
    skuCode,
    itemDescription,
}: SpkItemSkuColumnProps) {
    const typeProductLine = [typeCode, productItemName]
        .map((value) => value?.trim() ?? '')
        .filter(Boolean)
        .join(' | ');
    const sku = skuCode?.trim() ?? '';
    const description = itemDescription?.trim() ?? '';

    if (typeProductLine === '' && sku === '' && description === '') {
        return <>—</>;
    }

    return (
        <div className="spkItemTypeProductStack">
            {typeProductLine !== '' ? (
                <span className="spkItemTypeProductLine">{typeProductLine}</span>
            ) : null}
            {sku !== '' ? (
                <span className="spkItemSkuCode">{sku}</span>
            ) : null}
            {description !== '' ? (
                <span className="spkTableDescriptionItem">
                    {description}
                </span>
            ) : null}
        </div>
    );
}
