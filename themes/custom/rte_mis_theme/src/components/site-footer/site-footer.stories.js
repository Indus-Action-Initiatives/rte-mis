import './site-footer.scss';
import template from './site-footer.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
    title: 'Components/Site Footer',
    tags: ['autodocs'],
    render: (args) => renderTwig(template, args),
    parameters: { layout: 'fullscreen' },
    argTypes: {
        brand_subtitle: { control: 'text', description: 'Brand tagline' },
        helpline: { control: 'text', description: 'Helpline number' },
        email: { control: 'text', description: 'Contact email' },
        hours: { control: 'text', description: 'Working hours' },
        admin_login_url: { control: 'text', description: 'Admin login URL' },
        last_updated: { control: 'text', description: 'Last updated date' },
    },
};

export const Default = {
    args: {
        brand_subtitle: 'Right to Education Management Information System',
        helpline: '1800-XXX-XXXX',
        email: 'help@rte.gov.in',
        hours: 'Mon-Sat, 9 AM - 6 PM',
        admin_login_url: '#',
        last_updated: 'January 19, 2026',
        quick_links: [
            { label: 'Home', url: '#' },
            { label: 'About RTE', url: '#' },
            { label: 'Apply Now', url: '#' },
            { label: 'Check Status', url: '#' },
            { label: 'Find Schools', url: '#' },
        ],
        resources: [
            { label: 'Downloads', url: '#' },
            { label: 'FAQs', url: '#' },
            { label: 'Guidelines', url: '#' },
            { label: 'User Manual', url: '#' },
            { label: 'Video Tutorials', url: '#' },
        ],
        gov_links: [
            { label: 'Ministry of Education', url: '#' },
            { label: 'National Informatics Centre', url: '#' },
            { label: 'Digital India', url: '#' },
            { label: 'India.gov.in', url: '#' },
        ],
        footer_links: [
            { label: 'Privacy Policy', url: '#' },
            { label: 'Terms of Use', url: '#' },
            { label: 'Accessibility', url: '#' },
            { label: 'FOIA', url: '#' },
            { label: 'No FEAR Act', url: '#' },
        ],
        social_links: [
            { label: 'Facebook', url: '#' },
            { label: 'Twitter', url: '#' },
            { label: 'YouTube', url: '#' },
            { label: 'LinkedIn', url: '#' },
        ],
    },
};

export const MinimalFooter = {
    args: {
        brand_subtitle: 'Right to Education Management Information System',
        helpline: '1800-XXX-XXXX',
        email: 'help@rte.gov.in',
        hours: 'Mon-Sat, 9 AM - 6 PM',
        last_updated: 'January 19, 2026',
    },
};

export const CustomLinks = {
    args: {
        brand_subtitle: 'RTE Portal — Maharashtra',
        helpline: '1800-123-4567',
        email: 'maharashtra@rte.gov.in',
        hours: 'Mon-Fri, 10 AM - 5 PM',
        admin_login_url: '/admin',
        last_updated: 'February 27, 2026',
        quick_links: [
            { label: 'Home', url: '/' },
            { label: 'Apply', url: '/apply' },
            { label: 'Status', url: '/status' },
        ],
        resources: [
            { label: 'FAQs', url: '/faq' },
            { label: 'Help', url: '/help' },
        ],
        gov_links: [
            { label: 'Maharashtra.gov.in', url: '#' },
        ],
        footer_links: [
            { label: 'Privacy', url: '/privacy' },
            { label: 'Terms', url: '/terms' },
        ],
    },
};
