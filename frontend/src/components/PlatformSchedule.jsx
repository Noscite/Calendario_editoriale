import {
  Linkedin, Instagram, Facebook, Mail, Target,
} from 'lucide-react';

/**
 * <PlatformSchedule /> — vista compatta dello scheduling ottimale per piattaforma.
 *
 * Estratto come copia 1:1 da BuyerPersonasStep.jsx (PR pre-PR-WIZARD-2)
 * per riuso nel wizard Project nuovo. Il file legacy resta invariato.
 *
 * Props:
 *   - platform:  string — 'linkedin' | 'instagram' | 'facebook' | 'newsletter' | ...
 *   - strategy:  object { optimal_slots: [{day, time, priority}], avoid: [...] }
 *   - readOnly:  boolean (default true) — placeholder per future affordances di edit
 */

const platformIcons = {
  linkedin: Linkedin,
  instagram: Instagram,
  facebook: Facebook,
  newsletter: Mail,
};

const platformColors = {
  linkedin: 'bg-blue-600',
  instagram: 'bg-gradient-to-r from-purple-500 to-pink-500',
  facebook: 'bg-blue-500',
  newsletter: 'bg-amber-500',
};

const days = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

// eslint-disable-next-line no-unused-vars
export default function PlatformSchedule({ platform, strategy, readOnly = true }) {
  const Icon = platformIcons[platform] || Target;
  const colorClass = platformColors[platform] || 'bg-gray-500';
  const safeStrategy = strategy ?? {};

  return (
    <div className="p-4 bg-gray-50 rounded-lg">
      <div className="flex items-center gap-2 mb-3">
        <div className={`w-8 h-8 ${colorClass} rounded-lg flex items-center justify-center`}>
          <Icon className="w-4 h-4 text-white" />
        </div>
        <span className="font-medium capitalize">{platform}</span>
      </div>

      {safeStrategy.optimal_slots?.slice(0, 3).map((slot, idx) => (
        <div key={idx} className="flex items-center justify-between text-sm py-1">
          <span className="text-gray-600">{days[slot.day]}</span>
          <span className="font-mono text-gray-800">{slot.time}</span>
        </div>
      ))}

      {safeStrategy.avoid && safeStrategy.avoid.length > 0 && (
        <p className="mt-2 text-xs text-gray-400">
          Evitare: {safeStrategy.avoid.slice(0, 2).join(', ')}
        </p>
      )}
    </div>
  );
}
