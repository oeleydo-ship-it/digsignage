import {
    Accessibility,
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    ArrowUpDown,
    Baby,
    Bath,
    Bed,
    BookOpen,
    Briefcase,
    Building2,
    Bus,
    CakeSlice,
    Calendar,
    Camera,
    Car,
    Clock,
    CloudRain,
    Coffee,
    Cross,
    Dumbbell,
    Flame,
    Gift,
    GraduationCap,
    Heart,
    HeartPulse,
    IceCreamCone,
    Info,
    Leaf,
    Mail,
    MapPin,
    Megaphone,
    Music,
    ParkingCircle,
    Percent,
    Phone,
    Pill,
    Pizza,
    Plane,
    Presentation,
    QrCode,
    Recycle,
    ShieldCheck,
    ShoppingBag,
    Smile,
    Sparkles,
    Star,
    Stethoscope,
    Sun,
    Tag,
    ThumbsUp,
    TrainFront,
    TriangleAlert,
    Trophy,
    Users,
    UtensilsCrossed,
    Wifi,
    Wine,
    type LucideIcon,
} from 'lucide-react';

export type DesignIconGroup =
    | 'Wayfinding'
    | 'Food & drink'
    | 'Health'
    | 'Workplace'
    | 'Retail'
    | 'Travel'
    | 'Status'
    | 'Lifestyle';

/**
 * Curated icon set for the designer's Icon element.
 *
 * Icons are imported individually rather than through lucide's dynamic
 * loader so the player bundle only carries what signage layouts use. Keys
 * are stored in documents, so never rename one; add new keys instead.
 */
export const DESIGN_ICONS: Record<
    string,
    { label: string; group: DesignIconGroup; Icon: LucideIcon }
> = {
    'arrow-right': {
        label: 'Arrow right',
        group: 'Wayfinding',
        Icon: ArrowRight,
    },
    'arrow-left': { label: 'Arrow left', group: 'Wayfinding', Icon: ArrowLeft },
    'arrow-up': { label: 'Arrow up', group: 'Wayfinding', Icon: ArrowUp },
    'arrow-down': { label: 'Arrow down', group: 'Wayfinding', Icon: ArrowDown },
    elevator: { label: 'Lifts', group: 'Wayfinding', Icon: ArrowUpDown },
    'map-pin': { label: 'Location', group: 'Wayfinding', Icon: MapPin },
    accessibility: {
        label: 'Accessible',
        group: 'Wayfinding',
        Icon: Accessibility,
    },
    restroom: { label: 'Restroom', group: 'Wayfinding', Icon: Bath },
    baby: { label: 'Baby change', group: 'Wayfinding', Icon: Baby },
    parking: { label: 'Parking', group: 'Wayfinding', Icon: ParkingCircle },
    wifi: { label: 'Wi-Fi', group: 'Wayfinding', Icon: Wifi },
    info: { label: 'Information', group: 'Wayfinding', Icon: Info },

    coffee: { label: 'Coffee', group: 'Food & drink', Icon: Coffee },
    dining: { label: 'Dining', group: 'Food & drink', Icon: UtensilsCrossed },
    pizza: { label: 'Pizza', group: 'Food & drink', Icon: Pizza },
    wine: { label: 'Wine', group: 'Food & drink', Icon: Wine },
    dessert: { label: 'Dessert', group: 'Food & drink', Icon: CakeSlice },
    'ice-cream': {
        label: 'Ice cream',
        group: 'Food & drink',
        Icon: IceCreamCone,
    },

    stethoscope: { label: 'Clinic', group: 'Health', Icon: Stethoscope },
    heartbeat: { label: 'Cardiology', group: 'Health', Icon: HeartPulse },
    pharmacy: { label: 'Pharmacy', group: 'Health', Icon: Pill },
    'first-aid': { label: 'First aid', group: 'Health', Icon: Cross },
    fitness: { label: 'Fitness', group: 'Health', Icon: Dumbbell },

    office: { label: 'Office', group: 'Workplace', Icon: Building2 },
    briefcase: { label: 'Business', group: 'Workplace', Icon: Briefcase },
    team: { label: 'Team', group: 'Workplace', Icon: Users },
    meeting: { label: 'Meeting', group: 'Workplace', Icon: Presentation },
    calendar: { label: 'Calendar', group: 'Workplace', Icon: Calendar },
    clock: { label: 'Clock', group: 'Workplace', Icon: Clock },
    phone: { label: 'Phone', group: 'Workplace', Icon: Phone },
    mail: { label: 'Email', group: 'Workplace', Icon: Mail },
    'qr-code': { label: 'QR code', group: 'Workplace', Icon: QrCode },
    education: { label: 'Education', group: 'Workplace', Icon: GraduationCap },
    book: { label: 'Library', group: 'Workplace', Icon: BookOpen },

    shopping: { label: 'Shopping', group: 'Retail', Icon: ShoppingBag },
    tag: { label: 'Price tag', group: 'Retail', Icon: Tag },
    discount: { label: 'Discount', group: 'Retail', Icon: Percent },
    gift: { label: 'Gift', group: 'Retail', Icon: Gift },
    sparkles: { label: 'New', group: 'Retail', Icon: Sparkles },

    plane: { label: 'Flights', group: 'Travel', Icon: Plane },
    bus: { label: 'Bus', group: 'Travel', Icon: Bus },
    train: { label: 'Train', group: 'Travel', Icon: TrainFront },
    car: { label: 'Car', group: 'Travel', Icon: Car },
    bed: { label: 'Rooms', group: 'Travel', Icon: Bed },

    warning: { label: 'Warning', group: 'Status', Icon: TriangleAlert },
    safety: { label: 'Safety', group: 'Status', Icon: ShieldCheck },
    fire: { label: 'Fire', group: 'Status', Icon: Flame },
    announcement: { label: 'Announcement', group: 'Status', Icon: Megaphone },
    recycle: { label: 'Recycling', group: 'Status', Icon: Recycle },

    star: { label: 'Star', group: 'Lifestyle', Icon: Star },
    heart: { label: 'Heart', group: 'Lifestyle', Icon: Heart },
    trophy: { label: 'Trophy', group: 'Lifestyle', Icon: Trophy },
    'thumbs-up': { label: 'Thumbs up', group: 'Lifestyle', Icon: ThumbsUp },
    smile: { label: 'Smile', group: 'Lifestyle', Icon: Smile },
    music: { label: 'Music', group: 'Lifestyle', Icon: Music },
    camera: { label: 'Photo', group: 'Lifestyle', Icon: Camera },
    leaf: { label: 'Nature', group: 'Lifestyle', Icon: Leaf },
    sun: { label: 'Sunny', group: 'Lifestyle', Icon: Sun },
    rain: { label: 'Rain', group: 'Lifestyle', Icon: CloudRain },
};

export const DEFAULT_DESIGN_ICON = 'star';

export function designIcon(key: unknown) {
    return (
        (typeof key === 'string' ? DESIGN_ICONS[key] : undefined) ??
        DESIGN_ICONS[DEFAULT_DESIGN_ICON]
    );
}
