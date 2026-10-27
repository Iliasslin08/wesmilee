import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

export function Etoiles({ note, className }: { note: number; className?: string }) {
    return (
        <span className={cn('inline-flex align-middle', className)} title={`${note}/5`}>
            {[1, 2, 3, 4, 5].map((i) => (
                <Star key={i} className={cn('size-4', i <= note ? 'fill-amber-400 text-amber-400' : 'text-slate-300')} />
            ))}
        </span>
    );
}
