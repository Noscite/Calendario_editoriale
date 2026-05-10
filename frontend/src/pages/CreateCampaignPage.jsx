import { useEffect, useState } from 'react';
import CampaignForm from '../components/CampaignForm';
import { brands as brandsApi } from '../services/api';

export default function CreateCampaignPage() {
  const [brands, setBrands] = useState([]);

  useEffect(() => {
    brandsApi.list()
      .then(res => setBrands(res.data?.data || res.data || []))
      .catch(() => setBrands([]));
  }, []);

  return <CampaignForm brands={brands} />;
}
