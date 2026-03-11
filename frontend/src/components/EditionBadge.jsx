import { Layers } from 'lucide-react';

export default function EditionBadge({ editionNumber, size = 'sm' }) {
  if (!editionNumber) return null;

  const sizeClasses = size === 'sm'
    ? 'text-xs px-2 py-0.5'
    : 'text-sm px-3 py-1';

  return (
    <span className={`inline-flex items-center gap-1 ${sizeClasses} bg-[#3DAFA8]/10 text-[#3DAFA8] rounded-full font-medium`}>
      <Layers size={size === 'sm' ? 12 : 14} />
      Ed. {editionNumber}
    </span>
  );
}
