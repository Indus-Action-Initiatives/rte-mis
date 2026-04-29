import './school-card.scss';
import template from './school-card.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/School Card',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    udise_code: { control: 'text' },
    school_name: { control: 'text' },
    location: { control: 'text' },
    pincode: { control: 'text' },
    url: { control: 'text' },
  },
};

export const Default = {
  args: {
    udise_code: '27250101001',
    school_name: 'Zilla Parishad Primary School Ghodegaon',
    location: 'Pune » Ambegaon » Ambegaon Gram Panchayat » Ambegaon Habitation',
    pincode: '411040',
    school_type: 'Government',
    url: '#',
  },
};

export const LongName = {
  args: {
    ...Default.args,
    school_name: 'St. Mary Convent High School and Junior College of Arts and Commerce, Rajgurunagar',
    school_type: 'Private Unaided',
  },
};
