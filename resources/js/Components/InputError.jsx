export default function InputError({ message, className = '', as: Tag = 'p', tone = 'error', ...props }) {
    if (!message) return null;

    const toneClass = tone === 'muted' ? 'text-gray-500' : 'text-red-600';

    return (
        <Tag {...props} className={`text-sm ${toneClass} ` + className}>
            {message}
        </Tag>
    );
}
