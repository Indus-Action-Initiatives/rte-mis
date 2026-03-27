import './feature-cards.scss';
import template from './feature-cards.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Feature Cards',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    cards: { control: 'object' },
  },
};

const defaultCards = [
  {
    icon: 'education',
    title: 'Free Education',
    description: 'Complete free education from Class 1 to Class 8 with no fees or charges',
  },
  {
    icon: 'reservation',
    title: '25% Reservation',
    description: 'Private schools reserve seats for EWS and disadvantaged children',
  },
  {
    icon: 'location',
    title: 'Neighbourhood Schools',
    description: 'Schools within 1km (urban) or 3km (rural) from your residence',
  },
];

export const Default = {
  args: { cards: defaultCards },
};

export const TwoCards = {
  args: {
    cards: defaultCards.slice(0, 2),
  },
};

export const AllIcons = {
  name: 'All Icon Variants',
  args: {
    cards: [
      { icon: 'education',   title: 'Education',  description: 'Book icon example' },
      { icon: 'reservation', title: 'Reservation', description: 'People icon example' },
      { icon: 'location',    title: 'Location',    description: 'Pin icon example' },
      { icon: 'calendar',    title: 'Calendar',    description: 'Calendar icon example' },
      { icon: 'document',    title: 'Document',    description: 'Document icon example' },
      { icon: 'info',        title: 'Info',        description: 'Info icon example' },
    ],
  },
};
