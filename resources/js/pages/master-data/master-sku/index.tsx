import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import documentIcon from '@ui5/webcomponents-icons/dist/document.js';
import folderIcon from '@ui5/webcomponents-icons/dist/folder.js';
import folderFullIcon from '@ui5/webcomponents-icons/dist/folder-full.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { index as masterSkuIndex } from '@/routes/master-data/master-sku';
import { Input } from '@/components/ui/input';

type SkuLeaf = {
    id: number;
    skuCode: string;
    itemOriginal: string;
};

type HierarchyNode = {
    key: string;
    label: string;
    type:
        | 'category'
        | 'name'
        | 'size'
        | 'stone_shape'
        | 'stone_type'
        | 'diamond_type';
    count: number;
    children: HierarchyNode[];
    skus: SkuLeaf[];
};

type MasterSkuIndexProps = {
    tree: HierarchyNode[];
    filters: {
        search: string;
    };
};

const TYPE_LABEL: Record<HierarchyNode['type'], string> = {
    category: 'Kategori',
    name: 'Nama',
    size: 'Ukuran',
    stone_shape: 'Shape',
    stone_type: 'Stone Type',
    diamond_type: 'Diamond Type',
};

function collectExpandableKeys(nodes: HierarchyNode[]): string[] {
    const keys: string[] = [];

    const walk = (items: HierarchyNode[]) => {
        for (const node of items) {
            if (node.children.length > 0 || node.skus.length > 0) {
                keys.push(node.key);
            }

            if (node.children.length > 0) {
                walk(node.children);
            }
        }
    };

    walk(nodes);

    return keys;
}

function FolderRow({
    node,
    depth,
    expanded,
    onToggle,
}: {
    node: HierarchyNode;
    depth: number;
    expanded: Set<string>;
    onToggle: (key: string) => void;
}) {
    const isOpen = expanded.has(node.key);
    const hasChildren = node.children.length > 0 || node.skus.length > 0;

    return (
        <div className="skuTreeNode">
            <button
                type="button"
                className={`skuTreeRow ${isOpen ? 'is-open' : ''}`}
                style={{ paddingLeft: `${0.75 + depth * 1.15}rem` }}
                onClick={() => {
                    if (hasChildren) {
                        onToggle(node.key);
                    }
                }}
                aria-expanded={hasChildren ? isOpen : undefined}
            >
                <span
                    className={`skuTreeTwist ${hasChildren ? '' : 'is-leaf'}`}
                    aria-hidden
                />
                <Icon
                    name={isOpen && hasChildren ? folderFullIcon : folderIcon}
                    mode="Decorative"
                    className="skuTreeFolderIcon"
                />
                <span className="skuTreeLabel">{node.label}</span>
                <span className="skuTreeMeta">
                    {TYPE_LABEL[node.type]} · {node.count}
                </span>
            </button>

            {isOpen ? (
                <div className="skuTreeChildren">
                    {node.children.map((child) => (
                        <FolderRow
                            key={child.key}
                            node={child}
                            depth={depth + 1}
                            expanded={expanded}
                            onToggle={onToggle}
                        />
                    ))}
                    {node.skus.map((sku) => (
                        <div
                            key={sku.id}
                            className="skuTreeSkuRow"
                            style={{
                                paddingLeft: `${0.75 + (depth + 1) * 1.15}rem`,
                            }}
                        >
                            <Icon
                                name={documentIcon}
                                mode="Decorative"
                                className="skuTreeFileIcon"
                            />
                            <div className="skuTreeSkuBody">
                                <div className="skuTreeSkuCode">
                                    {sku.skuCode}
                                </div>
                                <div className="skuTreeSkuName">
                                    {sku.itemOriginal || '—'}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

export default function MasterSkuIndex({ tree, filters }: MasterSkuIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);
    const [expanded, setExpanded] = useState<Set<string>>(() => new Set());

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    useEffect(() => {
        if (filters.search.trim() !== '') {
            setExpanded(new Set(collectExpandableKeys(tree)));

            return;
        }

        setExpanded(new Set());
    }, [tree, filters.search]);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            router.get(
                masterSkuIndex.url({
                    query: {
                        search: searchQuery || undefined,
                    },
                }),
                {},
                {
                    preserveState: true,
                    replace: true,
                },
            );
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [searchQuery, filters.search]);

    const totalSkus = useMemo(() => {
        const sum = (nodes: HierarchyNode[]): number =>
            nodes.reduce((carry, node) => carry + node.count, 0);

        return sum(tree);
    }, [tree]);

    const toggle = (key: string) => {
        setExpanded((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    };

    const expandAll = () => {
        setExpanded(new Set(collectExpandableKeys(tree)));
    };

    const collapseAll = () => {
        setExpanded(new Set());
    };

    return (
        <>
            <Head title="Master SKU" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">Master SKU</h1>
                        <p className="masterDataSubtitle">
                            Hierarki prefix: Kategori → Nama → Ukuran → Shape →
                            Stone Type → Diamond Type (warna emas dilewati).
                            {` ${totalSkus} SKU aktif.`}
                        </p>
                    </div>
                    <div className="skuTreeToolbarActions">
                        <button
                            type="button"
                            className="masterDataLinkBtn"
                            onClick={expandAll}
                        >
                            Buka semua
                        </button>
                        <button
                            type="button"
                            className="masterDataLinkBtn"
                            onClick={collapseAll}
                        >
                            Tutup semua
                        </button>
                    </div>
                </div>

                <div className="spkTableCard">
                    <div className="spkTableToolbar">
                        <div className="spkTableToolbarLeft">
                            <Input
                                className="masterDataSearch"
                                value={searchQuery}
                                placeholder="Cari SKU, kategori, nama..."
                                onChange={(event) =>
                                    setSearchQuery(event.target.value)
                                }
                            />
                        </div>
                    </div>

                    {tree.length === 0 ? (
                        <div className="skuTreeEmpty">
                            Tidak ada SKU yang cocok.
                        </div>
                    ) : (
                        <div className="skuTree">
                            {tree.map((node) => (
                                <FolderRow
                                    key={node.key}
                                    node={node}
                                    depth={0}
                                    expanded={expanded}
                                    onToggle={toggle}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

MasterSkuIndex.layout = {
    activeMenu: 'Master SKU',
    pageTitle: 'Master SKU',
};
