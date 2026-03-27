import './two-column.scss';
import template from './two-column.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Two Column',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: 'text' },
    description: { control: 'text' },
    link_url: { control: 'text' },
    link_text: { control: 'text' },
    image_url: { control: 'text' },
    image_alt: { control: 'text' },
    image_position: {
      control: 'radio',
      options: ['right', 'left'],
    },
  },
};

const defaultArgs = {
  title: 'What is RTE Section 12(1)(c)?',
  description:
    'The Right to Education Act, 2009 ensures free and compulsory education for all children aged 6 to 14 years. Section 12(1)(c) mandates private schools to reserve 25% of entry‑level seats for children from economically weaker sections and disadvantaged groups.',
  link_url: '#read-more',
  link_text: 'Read More',
  image_url:
    'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=800&q=80',
  image_alt: 'Students in school uniform',
  image_position: 'right',
};

export const ImageRight = {
  args: {
    ...defaultArgs,
    image_position: 'right',
  },
};

export const ImageLeft = {
  args: {
    ...defaultArgs,
    image_position: 'left',
  },
};

export const NoButton = {
  args: {
    ...defaultArgs,
    link_url: '',
    link_text: '',
  },
};
