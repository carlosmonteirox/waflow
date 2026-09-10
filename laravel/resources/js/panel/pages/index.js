import ApiKeysPage from './ApiKeysPage.vue';
import AdminDashboardPage from './AdminDashboardPage.vue';
import AdminDirectoryPage from './AdminDirectoryPage.vue';
import AdminPartnersPage from './AdminPartnersPage.vue';
import AdminPlansPage from './AdminPlansPage.vue';
import BillingPage from './BillingPage.vue';
import ContactsPage from './ContactsPage.vue';
import DashboardPage from './DashboardPage.vue';
import DevelopersPage from './DevelopersPage.vue';
import FlowsPage from './FlowsPage.vue';
import InstancesPage from './InstancesPage.vue';
import MessagesPage from './MessagesPage.vue';
import PortalsPage from './PortalsPage.vue';
import PartnerCustomersPage from './PartnerCustomersPage.vue';
import PartnerDashboardPage from './PartnerDashboardPage.vue';
import PartnerFinancePage from './PartnerFinancePage.vue';
import PartnerSettingsPage from './PartnerSettingsPage.vue';
import SettingsPage from './SettingsPage.vue';
import TeamPage from './TeamPage.vue';
import WebhooksPage from './WebhooksPage.vue';

export const panelPages = {
    'admin-dashboard': AdminDashboardPage,
    'admin-companies': AdminDirectoryPage,
    'admin-users': AdminDirectoryPage,
    'admin-partners': AdminPartnersPage,
    'admin-plans': AdminPlansPage,
    'admin-subscriptions': AdminDirectoryPage,
    'api-keys': ApiKeysPage,
    billing: BillingPage,
    contacts: ContactsPage,
    dashboard: DashboardPage,
    developers: DevelopersPage,
    flows: FlowsPage,
    instances: InstancesPage,
    messages: MessagesPage,
    portals: PortalsPage,
    'partner-customers': PartnerCustomersPage,
    'partner-dashboard': PartnerDashboardPage,
    'partner-finance': PartnerFinancePage,
    'partner-settings': PartnerSettingsPage,
    settings: SettingsPage,
    team: TeamPage,
    webhooks: WebhooksPage,
};
