export type Tone = 'default' | 'success' | 'warning' | 'info' | 'neutral';

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
    tone: Tone;
    description: string;
}

export interface PaginationData {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    previous_page_url: string | null;
    next_page_url: string | null;
}

export interface SelectOption {
    label: string;
    value: string;
}

export interface AssigneeOption {
    id: number;
    name: string;
}

export interface AppliedFilters {
    status: string;
    search: string;
    date_from: string;
    date_to: string;
}

export interface CallMessage {
    caller_name: string | null;
    caller_number: string | null;
    message_text: string | null;
    recording_url: string | null;
    recording_duration: number | null;
    workflow_status: string | null;
    workflow_status_label: string | null;
    workflow_status_tone: Tone | null;
    assigned_to_user_id: number | null;
    assigned_to_name: string | null;
    handled_by_name: string | null;
    handled_at: string | null;
    callback_due_at: string | null;
}

export interface CallStatusEvent {
    received_at: string | null;
    call_status: string | null;
    call_duration_seconds: number | null;
    dial_call_status: string | null;
    dial_call_duration_seconds: number | null;
    dial_call_sid: string | null;
    callback_source: string | null;
    sequence_number: string | number | null;
}

export interface CallItem {
    id: number;
    external_sid: string | null;
    status: string;
    status_label: string;
    tone: Tone;
    direction: string | null;
    from_number: string | null;
    to_number: string | null;
    phone_label: string | null;
    started_at: string | null;
    ended_at: string | null;
    duration_seconds: number | null;
    summary: string | null;
    transfer_failure_status: string | null;
    fallback_target: string | null;
    recent_status_events: CallStatusEvent[];
    message: CallMessage | null;
}

export interface CallDetailItem extends CallItem {
    tenant_name: string;
}

export interface InboxMessageItem {
    id: number;
    call_id: number;
    call_external_sid: string | null;
    caller: string;
    phone: string;
    excerpt: string;
    message_text: string | null;
    recording_url: string | null;
    recording_duration: number | null;
    status: string;
    status_label: string;
    status_tone: Tone;
    call_status: string | null;
    call_status_label: string | null;
    priority: string;
    created_at: string | null;
    summary: string | null;
    assigned_to_user_id: number | null;
    assigned_to_name: string | null;
    handled_by_name: string | null;
    handled_at: string | null;
    callback_due_at: string | null;
}

export interface ActivityItem {
    id: number;
    event_type: string;
    title: string;
    description: string | null;
    tone: Tone;
    happened_at: string | null;
    user_name: string | null;
    call_id: number | null;
    call_message_id: number | null;
}

export interface IntegrationItem {
    name: string;
    status: string;
    tone: Tone;
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
