import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    communities: CommunityLink[] | null;
    unreadNotifications: number;
    unreadChats: number;
    moderationQueue: number;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    features: { google: boolean };
    flash: { success: string | null; error: string | null };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    avatar?: string | null;
    bio?: string | null;
    is_admin?: boolean;
    restricted?: boolean;
    email_verified_at: string | null;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface PublicUser {
    id: number;
    name: string;
    username: string;
    avatar: string | null;
    level?: number | null;
}

export interface CommunityLink {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
}

export type Role = 'owner' | 'admin' | 'moderator' | 'member';

export interface Community extends CommunityLink {
    tagline: string | null;
    cover: string | null;
    privacy: 'public' | 'private';
    access_type: 'free' | 'paid';
    level_names: (string | null)[];
    members_count: number;
    media: { images: boolean; gifs: boolean; comments: boolean; max_images: number; max_kb: number };
    membership: {
        role: Role;
        status: 'active' | 'pending' | 'banned' | 'expired';
        points: number;
        level: number;
    } | null;
    can: { participate: boolean; moderate: boolean; manage: boolean; email_post: boolean };
}

export interface Category {
    id: number;
    name: string;
    slug: string;
    emoji: string | null;
    admin_only?: boolean;
}

export interface Post {
    id: number;
    title: string;
    body: string;
    is_pinned: boolean;
    comments_disabled: boolean;
    likes_count: number;
    comments_count: number;
    liked: boolean;
    author: PublicUser;
    attachments: PostAttachment[];
    category: Category | null;
    recent_commenters: PublicUser[];
    created_at: string;
    last_activity_at: string;
    can: { update: boolean; delete: boolean; pin: boolean };
}

export interface PostAttachment {
    path: string;
    url: string;
    gif: boolean;
}

export interface Comment {
    id: number;
    parent_id: number | null;
    body: string;
    attachment: PostAttachment | null;
    likes_count: number;
    liked: boolean;
    author: PublicUser;
    created_at: string;
    replies?: Comment[];
    can: { update: boolean; delete: boolean };
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface CourseCard {
    id: number;
    title: string;
    description: string | null;
    cover: string | null;
    access: 'open' | 'level';
    unlock_level: number | null;
    is_published: boolean;
    lessons_count?: number;
    unlocked?: boolean;
    progress: number;
}

export interface LessonResource {
    label: string;
    url: string;
}

export interface CourseModule {
    id: number;
    title: string;
    lessons: { id: number; title: string; is_published: boolean; completed: boolean }[];
}

export interface Lesson {
    id: number;
    title: string;
    body: string | null;
    video_url: string | null;
    resources: LessonResource[] | null;
    is_published: boolean;
    course_module_id: number;
    completed: boolean;
    previous_id: number | null;
    next_id: number | null;
}

export interface EventOccurrence {
    key: string;
    event_id: number;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    first_starts_at: string;
    duration_minutes: number;
    recurrence: 'none' | 'weekly';
    repeat_until: string | null;
    timezone: string;
    location: string | null;
    host: PublicUser;
}

export interface Plan {
    id: number;
    name: string;
    interval: 'month' | 'year' | 'one_time';
    amount: number;
    currency: string;
    is_active: boolean;
    gateway: 'stripe' | 'razorpay';
}
