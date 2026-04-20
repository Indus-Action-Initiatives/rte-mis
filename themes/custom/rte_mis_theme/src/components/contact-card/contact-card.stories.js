import './contact-card.scss';
import template from './contact-card.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Contact Card',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {
    department_name: {
      control: 'text',
      description: 'The name of the department or division',
    },
    address: {
      control: 'text',
      description: 'Physical address (HTML allowed)',
    },
    phone: {
      control: 'text',
      description: 'Contact phone number',
    },
    email: {
      control: 'text',
      description: 'Contact email address',
    },
    jurisdiction: {
      control: 'text',
      description: 'Optional jurisdiction information',
    },
  },
};

export const Default = {
  args: {
    department_name: 'School Education and Sports Department',
    address: 'Government of Maharashtra, Mantralaya (Extension), Room No. 432<br>Madam Cama Road, Hutatma Rajguru Chowk,<br>Mantralaya, Mumbai - 400032',
    phone: '(022) 22046261',
    email: 'acs.schedu@maharashtra.gov.in',
  },
};

export const WithJurisdiction = {
  args: {
    department_name: 'Dy. Director of Education, Mumbai Division',
    address: 'Mittal Foundation Trust, Ayurvedic Hospital Building, 8th Floor, Netaji Subhash Road, Near Charni Road Station, Behind Kaivalyadham Yoga Centre, Marine Drive, Mumbai - 400002',
    phone: '-',
    email: 'dydemumbai@yahoo.com',
    jurisdiction: 'Mumbai (North, South, West), Thane, Raigad, Palghar',
  },
};
