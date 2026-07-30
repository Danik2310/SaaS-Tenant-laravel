import TenantLayout from '@/Layouts/TenantLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { FormCard, FormInput, ButtonPrimary, ButtonSecondary, FormActions, SelectInput } from '@/Components/FormElements';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

export default function ProductForm({ product, categories }) {
    const isEditing = !!product;

    const { data, setData, errors, post, put, processing } = useForm({
        name: product?.name || '',
        sku: product?.sku || '',
        description: product?.description || '',
        price: product?.price || '',
        cost: product?.cost || '',
        category_id: product?.category_id || '',
        active: product?.active ?? true,
        image: null,
    });

    const existingImage = product?.images?.[0];

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route('tenant.products.update', product.id));
        } else {
            post(route('tenant.products.store'));
        }
    };

    return (
        <TenantLayout>
            <Head title={isEditing ? 'Edit Product' : 'New Product'} />

            <Box sx={{ mb: 3 }}>
                <Typography variant="h5" sx={{ fontWeight: 700, color: '#0f172a' }}>
                    {isEditing ? 'Edit Product' : 'New Product'}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                    <Link href={route('tenant.products.index')} style={{ color: '#3b82f6' }}>Products</Link>
                    {' / '}{isEditing ? product.name : 'New'}
                </Typography>
            </Box>

            <FormCard title={isEditing ? `Editing: ${product.name}` : 'Create Product'}
                subtitle={isEditing ? `SKU: ${product.sku}` : 'Add a new product to your inventory'}>
                <form onSubmit={handleSubmit}>
                    <FormInput label="Product Name" required error={errors.name}>
                        <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Product name" />
                    </FormInput>

                    <FormInput label="SKU" required hint="Unique stock keeping unit identifier" error={errors.sku}>
                        <input type="text" value={data.sku} onChange={(e) => setData('sku', e.target.value)} placeholder="e.g., PROD-001" />
                    </FormInput>

                    <FormInput label="Description" error={errors.description}>
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)}
                            placeholder="Product description" rows={3} />
                    </FormInput>

                    <Box sx={{ display: 'flex', gap: 2 }}>
                        <Box sx={{ flex: 1 }}>
                            <FormInput label="Price" required error={errors.price}>
                                <input type="number" step="0.01" min="0" value={data.price}
                                    onChange={(e) => setData('price', e.target.value)} placeholder="29.99" />
                            </FormInput>
                        </Box>
                        <Box sx={{ flex: 1 }}>
                            <FormInput label="Cost" error={errors.cost}>
                                <input type="number" step="0.01" min="0" value={data.cost}
                                    onChange={(e) => setData('cost', e.target.value)} placeholder="15.00" />
                            </FormInput>
                        </Box>
                    </Box>

                    <FormInput label="Category" error={errors.category_id}>
                        <SelectInput value={data.category_id} onChange={(e) => setData('category_id', e.target.value)}>
                            <option value="">No category</option>
                            {categories.map((cat) => (
                                <option key={cat.id} value={cat.id}>{cat.name}</option>
                            ))}
                        </SelectInput>
                    </FormInput>

                    <FormInput label="Image" error={errors.image}>
                        {existingImage && (
                            <Box sx={{ mb: 1 }}>
                                <img src={existingImage.url()} alt="Preview"
                                    style={{ maxWidth: 200, maxHeight: 150, borderRadius: 4, objectFit: 'cover' }} />
                            </Box>
                        )}
                        <input type="file" accept="image/jpeg,image/png,image/gif,image/webp"
                            onChange={(e) => setData('image', e.target.files[0])} />
                    </FormInput>

                    <FormInput label="Status">
                        <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                            <input type="checkbox" checked={data.active}
                                onChange={(e) => setData('active', e.target.checked)}
                                style={{ width: 16, height: 16, accentColor: '#3b82f6', cursor: 'pointer' }} />
                            <span style={{ fontSize: 14, color: '#334155' }}>Active (visible for sale)</span>
                        </label>
                    </FormInput>

                    <FormActions>
                        <Link href={route('tenant.products.index')}>
                            <ButtonSecondary>Cancel</ButtonSecondary>
                        </Link>
                        <ButtonPrimary type="submit" disabled={processing}>
                            {processing ? 'Saving...' : (isEditing ? 'Update Product' : 'Create Product')}
                        </ButtonPrimary>
                    </FormActions>
                </form>
            </FormCard>
        </TenantLayout>
    );
}
