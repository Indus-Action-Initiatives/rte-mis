import './hero-banner.scss';
import template from './hero-banner.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Hero Banner',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: 'text' },
    subtitle: { control: 'text' },
    benefits: { control: 'object' },
    primary_button_text: { control: 'text' },
    secondary_button_text: { control: 'text' },
    image_url: { control: 'text' },
    image_alt: { control: 'text' },
  },
};

export const Default = {
  args: {
    title: 'Right of Children to Free & Compulsory Education Act, 2009',
    subtitle: 'Apply for 25% reserved seats in private schools under RTE Section 12(1)(c). Free quality education for children from economically weaker sections.',
    benefits: [
      '100% free admission and education',
      'Transparent lottery-based allocation',
      'Simple online application process',
    ],
    primary_button_text: 'Apply for Admission',
    primary_button_url: '#apply',
    secondary_button_text: 'Check Eligibility',
    secondary_button_url: '#eligibility',
    image_url: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=1400&q=80',
    image_alt: 'Students in school uniform smiling',
  },
};
