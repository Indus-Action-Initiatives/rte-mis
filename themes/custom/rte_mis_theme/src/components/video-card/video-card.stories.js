import './video-card.scss';
import template from './video-card.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Video Card',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    thumbnail: { control: 'text' },
    title: { control: 'text' },
    description: { control: 'text' },
    updated_date: { control: 'text' },
    link_url: { control: 'text' },
    link_text: { control: 'text' },
  },
};

export const Default = {
  args: {
    thumbnail: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=2070&auto=format&fit=crop',
    title: 'How To Apply For RTE Admission',
    description: 'Get started with your RTE application. Our guide provides a clear, concise walkthrough.',
    updated_date: 'Updated January 24, 2026',
    link_url: '#',
    link_text: 'Learn More',
  },
};

export const Minimal = {
  args: {
    thumbnail: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=2070&auto=format&fit=crop',
    title: 'Video Without Description or Link',
    description: null,
    updated_date: 'Updated January 24, 2026',
    link_url: null,
    link_text: '',
  },
};
