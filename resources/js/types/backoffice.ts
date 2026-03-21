export interface TenantSummary {
    id: number;
    name: string;
    slug: string;
    locale: string;
    timezone: string;
}

export interface WorkspaceSummary {
    total_calls: number;
    voicemail_calls: number;
    after_hours_calls: number;
    messages_waiting: number;
    open_hours: string;
    transfer_enabled: boolean;
    notification_ready: boolean;
    configuration_score: number;
}

export interface ServiceStatus {
    label: string;
    tone: 'default' | 'success' | 'warning' | 'info' | 'neutral';
    description: string;
}

export interface CallMessage {
    caller_name: string | null;
    caller_number: string | null;
    message_text: string | null;
    recording_url: string | null;
}

export interface CallItem {
    id: number;
    status: string;
    status_label: string;
    tone: 'default' | 'success' | 'warning' | 'info' | 'neutral';
    direction: string | null;
    from_number: string | null;
    to_number: string | null;
    phone_label: string | null;
    started_at: string | null;
    ended_at: string | null;
    duration_seconds: number | null;
    summary: string | null;
    message: CallMessage | null;
}

export interface IntegrationItem {
    name: string;
    status: string;
    tone: 'default' | 'success' | 'warning' | 'info' | 'neutral';
    description: string;
}

export interface SettingsFormData {
    agent_name: string;
    welcome_message: string;
    after_hours_message: string;
    faq_content: string;
    transfer_phone_number: string;
    notification_email: string;
    opens_at: string | null;
    closes_at: string | null;
    business_days: string[];
    phone_number: string;
}
