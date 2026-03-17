import './announcement-bar.scss';
import template from './announcement-bar.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Announcement Bar',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    label: { control: 'text' },
    text: { control: 'text' },
    detail_url: { control: 'text' },
    detail_text: { control: 'text' },
    view_all_url: { control: 'text' },
    view_all_text: { control: 'text' },
  },
};

export const Default = {
  name: 'Default',
  args: {
    label: 'Announcements:',
    text: 'Applications for Academic Year 2026-27 are now open. Apply before March 31, 2026.',
    detail_url: '#',
    detail_text: 'View Details',
    view_all_url: '#',
    view_all_text: 'View all',
  }
};
