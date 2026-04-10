import './cards-grid.scss';
import template from './cards-grid.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Cards Grid',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: 'text' },
    card_type: { control: 'text' },
    items: { control: 'object' },
    view_all_link: { control: 'object' },
  },
};

const videoItems = [
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

const newsItems = [
  {
    image: 'https://images.unsplash.com/photo-1577701720272-680327fbcaf7?q=80&w=2070&auto=format&fit=crop',
    title: 'Guidelines For Implementation Of Section 12(1)(C) Of "The Right Of Children..."',
    date: 'December 26, 2025',
    url: '#',
  },
  {
    image: 'https://images.unsplash.com/photo-1509062522246-3755977927d7?q=80&w=2070&auto=format&fit=crop',
    title: 'Second Notification On Admission Rules',
    date: 'December 26, 2025',
    url: '#',
  },
  {
    image: 'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?q=80&w=2070&auto=format&fit=crop',
    title: 'Updated Circular for School Registrations',
    date: 'December 26, 2025',
    url: '#',
  },
];

export const VideosGrid = {
  args: {
    title: 'Tutorials & Guides',
    card_type: 'video-card',
    items: videoItems,
    view_all_link: {
      text: 'View All',
      url: '#'
    },
  },
};

export const NewsUpdatesGrid = {
  args: {
    title: 'Notifications & Circulars',
    card_type: 'news-card',
    items: newsItems,
    view_all_link: {
      text: 'View All',
      url: '#'
    },
  },
};
