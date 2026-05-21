import TenantLayout from '@/Layouts/TenantLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { FormCard, FormInput, ButtonPrimary, ButtonSecondary, FormActions, SelectInput } from '@/Components/FormElements';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

export default function InventoryForm({ movement, products, warehouses }) {
    const { data, setData, errors, post, processing } = useForm({
        product_id: '',
        warehouse_id: '',
        type: 'in',
        quantity: '',
        reason: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('tenant.inventory.store'));
    };

    return (
        <TenantLayout>
            <Head title="Record Movement" />

            <Box sx={{ mb: 3 }}>
                <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a' }}>
                    Record Inventory Movement
                </Typography>
                <Typography variant="body2" color="text.secondary">
                    <Link href={route('tenant.inventory.index')} style={{ color: '#3b82f6' }}>Movements</Link>
                    {' / New'}
                </Typography>
            </Box>

            <FormCard title="New Movement" subtitle="Record stock entering or leaving a warehouse">
                <form onSubmit={handleSubmit}>
                    <FormInput label="Product" required error={errors.product_id}>
                        <SelectInput value={data.product_id} onChange={(e) => setData('product_id', e.target.value)}>
                            <option value="">Select a product...</option>
                            {products.map((p) => (
                                <option key={p.id} value={p.id}>{p.name} ({p.sku})</option>
                            ))}
                        </SelectInput>
                    </FormInput>

                    <FormInput label="Warehouse" required error={errors.warehouse_id}>
                        <SelectInput value={data.warehouse_id} onChange={(e) => setData('warehouse_id', e.target.value)}>
                            <option value="">Select a warehouse...</option>
                            {warehouses.map((w) => (
                                <option key={w.id} value={w.id}>{w.name}{w.location ? ` — ${w.location}` : ''}</option>
                            ))}
                        </SelectInput>
                    </FormInput>

                    <FormInput label="Movement Type" required error={errors.type}>
                        <SelectInput value={data.type} onChange={(e) => setData('type', e.target.value)}>
                            <option value="in">Stock In (receiving)</option>
                            <option value="out">Stock Out (shipping)</option>
                            <option value="adjustment">Adjustment</option>
                        </SelectInput>
                    </FormInput>

                    <FormInput label="Quantity" required error={errors.quantity}>
                        <input type="number" min="1" value={data.quantity}
                            onChange={(e) => setData('quantity', e.target.value)}
                            placeholder="e.g., 10" />
                    </FormInput>

                    <FormInput label="Reason" error={errors.reason}>
                        <textarea value={data.reason} onChange={(e) => setData('reason', e.target.value)}
                            placeholder="Why is this movement happening?" rows={2} />
                    </FormInput>

                    <FormActions>
                        <Link href={route('tenant.inventory.index')}>
                            <ButtonSecondary>Cancel</ButtonSecondary>
                        </Link>
                        <ButtonPrimary type="submit" disabled={processing}>
                            {processing ? 'Recording...' : 'Record Movement'}
                        </ButtonPrimary>
                    </FormActions>
                </form>
            </FormCard>
        </TenantLayout>
    );
}
