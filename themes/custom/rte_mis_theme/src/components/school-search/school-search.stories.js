import './school-search.scss';
import './school-search.js';
import template from './school-search.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/School Search',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  parameters: {
    layout: 'fullscreen',
  },
  argTypes: {
    title_prefix: { control: 'text' },
    title_highlight: { control: 'text' },
    search_placeholder: { control: 'text' },
    search_action: { control: 'text' },
    filters: { control: 'object' },
  },
};

const defaultFilters = [
  {
    label: 'Select School',
    placeholder: 'School Name',
    name: 'school',
    options: [
      { value: 'school_1', label: 'School Name 1' },
      { value: 'school_2', label: 'School Name 2' },
      { value: 'school_3', label: 'School Name 3' },
      { value: 'school_4', label: 'Long School Name Goes Here' },
    ],
  },
  {
    label: 'Select PIN',
    placeholder: 'Select PIN code',
    name: 'pin',
    options: [
      { value: '411001', label: '411001' },
      { value: '411003', label: '411003' },
      { value: '411006', label: '411006' },
      { value: '411014', label: '411014' },
    ],
  },
  {
    label: 'Location',
    placeholder: 'Select Location',
    name: 'location',
    options: [
      { value: 'amritsar', label: 'Amritsar' },
      { value: 'ludhiana', label: 'Ludhiana' },
      { value: 'patiala', label: 'Patiala' },
      { value: 'jalandhar', label: 'Jalandhar' },
    ],
  },
];

export const Default = {
  args: {
    title_prefix: 'Search by',
    title_highlight: 'School Name, PIN Code or Location',
    search_placeholder: 'Find Schools',
    search_action: '#',
    filters: defaultFilters,
  },
};

export const NoFilters = {
  name: 'Search Only',
  args: {
    title_prefix: 'Search by',
    title_highlight: 'School Name or UDISE Code',
    search_placeholder: 'Enter school name or UDISE code…',
    search_action: '/search',
    filters: [],
  },
};

export const CustomTitle = {
  name: 'Custom Title',
  args: {
    title_prefix: 'Find your',
    title_highlight: 'Nearest School',
    search_placeholder: 'Type your area or PIN code…',
    search_action: '#',
    filters: defaultFilters,
  },
};
