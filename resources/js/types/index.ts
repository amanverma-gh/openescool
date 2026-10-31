import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    communities: CommunityLink[] | null;
    unreadNotifications: number;
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
    members_count: number;
    membership: {
        role: Role;
        status: 'active' | 'pending' | 'banned';
        points: number;
        level: number;
    } | null;
    can: { participate: boolean; moderate: boolean; manage: boolean };
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
    category: Category | null;
    recent_commenters: PublicUser[];
    created_at: string;
    last_activity_at: string;
    can: { update: boolean; delete: boolean; pin: boolean };
}

export interface Comment {
    id: number;
    parent_id: number | null;
    body: string;
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
