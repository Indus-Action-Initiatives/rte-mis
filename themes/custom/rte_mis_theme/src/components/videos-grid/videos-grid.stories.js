import './videos-grid.scss';
import template from './videos-grid.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Videos Grid',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: 'text' },
    items: { control: 'object' },
    view_all_url: { control: 'text' },
  },
};

const items = [
  {
    thumbnail: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=2070&auto=format&fit=crop',
    title: 'How To Apply For RTE Admission',
    description: 'Get started with your RTE application. Our guide provides a clear, concise walkthrough.',
    updated_date: 'Updated January 24, 2026',
    link_url: '#',
  },
  {
    thumbnail: 'https://images.unsplash.com/photo-1509062522246-3755977927d7?q=80&w=2070&auto=format&fit=crop',
    title: 'Step-By-Step Registration Process',
    description: 'A comprehensive guide to the registration process for RTE seats.',
    updated_date: 'Updated January 24, 2026',
    link_url: '#',
  },
  {
    thumbnail: 'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?q=80&w=2070&auto=format&fit=crop',
    title: 'Understanding Eligibility Criteria',
    description: 'Check if your child is eligible for the RTE scheme and what documents are required.',
    updated_date: 'Updated January 24, 2026',
    link_url: '#',
  },
];

export const Default = {
  args: {
    title: 'Tutorials & Guides',
    items: items,
    view_all_url: '#',
  },
};
