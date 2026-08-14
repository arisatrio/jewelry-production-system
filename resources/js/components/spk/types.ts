export type SpkStatus = 'Approved' | 'Pengajuan Approval' | (string & {});

export type SpkRow = {
    id: string;
    produksiNo: string;
    tipeProduksi: string;
    customer: string;
    item: string;
    description: string;
    itemId: string | null;
    orderDate: string;
    workEstimated: number | string;
    estimatedDelivery: string;
    status: SpkStatus;
    prosesTerakhir: string;
};

export type SpkWorkflowStatusKey =
    | 'draft'
    | 'confirmed'
    | 'inProgress'
    | 'done';

export type SpkWorkflowStatus = {
    key: SpkWorkflowStatusKey;
    label: string;
    stageIndex: number;
    isOverdue: boolean;
    stages: Array<{
        key: SpkWorkflowStatusKey;
        label: string;
    }>;
};

export type SpkDetail = SpkRow & {
    requestOrderNo: string;
    refSpkNo: string;
    description: string;
    qty: number | string;
    goldWeight: string | number;
    goldColor: string;
    goldContent: string;
    priority: string;
    statusOrder: string;
    notes: string;
    frameId: string;
    fileName: string;
    lastWeight: string | number;
    createdDate: string;
    createdBy: string;
    modifiedDate: string;
    modifiedBy: string;
    workflowStatus: SpkWorkflowStatus;
};

export type SpkItemDetail = {
    id: string | null;
    name: string;
    typeCode?: string;
    productItemName?: string;
    skuCode?: string;
    statusOrderLabel?: string;
    itemType?: string;
    itemVariance?: string;
    qty: number | string;
    diameter: string;
    dimensi: string;
    ringSize: string;
    diameterLengthRingSize: string;
    goldWeight: string;
    goldColor: string;
    jwcad3d: string;
    description: string;
    imageUrl: string | null;
    finishingType: string;
};

export type SpkNavigation = {
    position: number;
    total: number;
    previousSpkNo: string | null;
    nextSpkNo: string | null;
};

export type SpkStoneItem = {
    id: string;
    positionId?: string;
    positionName?: string;
    shape: string;
    shapeCode: string;
    shapeName: string;
    pcs: number;
    carat: string | number;
    caratPerPcs: string;
    totalCarat: string;
    size: string | number;
};

export type SpkProcessSource = {
    table: string;
    recordCount: number;
    records: Array<Record<string, unknown>>;
};

export type SpkProcessTab = {
    key: string;
    label: string;
    tables: string[];
    placement: 'proses-produksi' | 'main';
    recordCount: number;
    sources: SpkProcessSource[];
};

export type SpkDefaultProcessSelection = {
    mainSection: string;
    processKey: string;
};

export type SpkShrinkReportRow = {
    no: number;
    process: string;
    setorDate: string;
    startWeight: string | null;
    endWeight: string | null;
    shrink: string;
    shrinkPercent: string | null;
    tolerance: string | null;
    toleranceStatus: 'OK' | 'NOK' | null;
};

export type SpkShrinkGoldMaterial = {
    no: number;
    name: string;
    type: string;
    weight: string;
    notes: string | null;
};

export type SpkShrinkReport = {
    rows: SpkShrinkReportRow[];
    planningWeight: string | null;
    startWeight: string | null;
    endWeight: string | null;
    goldIssued: string | null;
    goldReturned: string | null;
    goldUsed: string | null;
    goldMaterials: SpkShrinkGoldMaterial[];
    totalShrink: string;
    totalShrinkPercent: string | null;
    totalLost: string | null;
    totalLostPercent: string | null;
    totalLabel: string;
};

export type SpkGoldReport = {
    issued: string | null;
    returned: string | null;
    used: string | null;
    difference: string | null;
    materials: SpkShrinkGoldMaterial[];
    totalLabel: string;
};

export type SpkStoneReportRow = {
    no: number;
    stone: string;
    pcsStart: number | null;
    pcsEnd: number | null;
    startCrt: string | null;
    endCrt: string | null;
    difference: string | null;
};

export type SpkStoneReport = {
    rows: SpkStoneReportRow[];
    totalStartCrt: string | null;
    totalEndCrt: string | null;
    totalDifference: string | null;
    totalLabel: string;
};

export type SpkProductionControlIdle = {
    no: number;
    fromProcess: string;
    toProcess: string;
    fromDate: string | null;
    toDate: string | null;
    idleLabel: string | null;
    idleMinutes: number | null;
};

export type SpkProductionControlReport = {
    leadTime: {
        startDate: string | null;
        endDate: string | null;
        durationLabel: string | null;
        durationDays: number | null;
        estimatedDays: number | null;
        varianceDays: number | null;
        varianceLabel: string | null;
    };
    idleTimes: SpkProductionControlIdle[];
    yieldPlanning: {
        planningWeight: string | null;
        endWeight: string | null;
        yieldPercent: string | null;
        goldUsed: string | null;
        goldYieldPercent: string | null;
    };
};

export type SpkCraftsmanReportCard = {
    no: number;
    craftsmanId: number | null;
    craftsmanName: string;
    process: string;
    workDuration: string | null;
    workDurationMinutes: number | null;
    sentAt: string | null;
    receivedAt: string | null;
    shrink: string | null;
};

export const SPK_TABLE_COLUMNS = [
    { key: 'action', label: '' },
    { key: 'produksiNo', label: 'Produksi No' },
    { key: 'tipeProduksi', label: 'Tipe Produksi' },
    { key: 'customer', label: 'Customer' },
    { key: 'item', label: 'Item' },
    { key: 'description', label: 'Description' },
    { key: 'orderDate', label: 'Order date' },
    { key: 'workEstimated', label: 'Work estimated' },
    { key: 'estimatedDelivery', label: 'Estimated delivery' },
    { key: 'status', label: 'Status' },
    { key: 'prosesTerakhir', label: 'Proses terakhir' },
] as const;
