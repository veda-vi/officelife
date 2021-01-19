<style lang="scss" scoped>
.avatar {
  border: 1px solid #e1e4e8 !important;
  padding: 10px;
  background-color: #fff;
  border-radius: 7px;

  img {
    width: 100%;
    height: auto;
  }

  &:hover {
    .change-avatar {
      display: block;
    }
  }
}

.change-avatar {
  box-shadow: 1px 1px 2px rgba(122, 122, 122, 0.17);
  opacity: 0.8;
  top: 10px;
  width: calc(100% - 20px);
}
</style>

<template>
  <div>
    <div class="db center mb4 avatar relative">
      <img :class="{'black-white':(employee.locked)}" loading="lazy" :src="employee.avatar" alt="avatar" />

      <div class="change-avatar absolute bg-white pa3 tc dn">
        <input ref="uploadedImg"
               type="file"
               class="form-control-file"
               name="photo"
               :disabled="hasReachedAccountStorageLimit"
               @change="uploadImg($event)"
        />
        Change avatar
      </div>
    </div>

    <sweet-modal ref="cropModal" :title="$t('people.avatar_crop_new_avatar_photo')" :blocking="true" :hide-close-button="true">
      <clipper-basic ref="clipper" :src="uploadedImgUrl" :ratio="1" :init-width="100" :init-height="100" />
      <div slot="button">
        <a class="btn" href="" @click.prevent="cancelCrop">
          {{ $t('app.cancel') }}
        </a>
        <a class="btn btn-primary" href="" @click.prevent="setCroppedImg">
          {{ $t('app.done') }}
        </a>
      </div>
    </sweet-modal>

    <personal-description
      :employee="employee"
      :permissions="permissions"
    />

    <employee-important-dates
      :employee="employee"
      :permissions="permissions"
    />

    <employee-gender-pronoun
      :employee="employee"
      :permissions="permissions"
    />

    <employee-status
      :employee="employee"
      :permissions="permissions"
    />

    <employee-contact
      :employee="employee"
      :permissions="permissions"
    />

    <profile-actions
      :employee="employee"
      :permissions="permissions"
    />
  </div>
</template>

<script>
import { clipperBasic } from 'vuejs-clipper';
import { SweetModal } from 'sweet-modal-vue';
import PersonalDescription from '@/Pages/Employee/Partials/PersonalDescription';
import EmployeeImportantDates from '@/Pages/Employee/Partials/EmployeeImportantDates';
import EmployeeStatus from '@/Pages/Employee/Partials/EmployeeStatus';
import EmployeeContact from '@/Pages/Employee/Partials/EmployeeContact';
import EmployeeGenderPronoun from '@/Pages/Employee/Partials/EmployeeGenderPronoun';
import ProfileActions from '@/Pages/Employee/Partials/ProfileActions';

export default {
  components: {
    clipperBasic,
    SweetModal,
    PersonalDescription,
    EmployeeImportantDates,
    EmployeeStatus,
    EmployeeContact,
    EmployeeGenderPronoun,
    ProfileActions,
  },

  props: {
    employee: {
      type: Object,
      default: null,
    },
    permissions: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      uploadedImgUrl: '',
      croppedImgUrl: '',
    };
  },

  methods: {
    uploadImg: function(e) {
      if (e.target.files.length !== 0) {
        if(this.uploadedImgUrl) {
          URL.revokeObjectURL(this.uploadedImgUrl);
        }
        this.uploadedImgUrl = window.URL.createObjectURL(e.target.files[0]);
        this.$refs.cropModal.open();
      }
    },

    setCroppedImg: function () {
      const canvas = this.$refs.clipper.clip();

      canvas.toBlob((blob) => {
        const input = this.$refs.uploadedImg;
        const file = new File([blob], input.files[0].name, { type: 'image/jpeg' });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        input.files = dataTransfer.files;
        this.croppedImgUrl = window.URL.createObjectURL(blob);
      }, 'image/jpeg', 1);
      this.$refs.cropModal.close();
    },

    cancelCrop() {
      const dataTransfer = new DataTransfer();
      this.$refs.uploadedImg.files = dataTransfer.files;
      this.croppedImgUrl = '';
      this.$refs.cropModal.close();
    },
  },
};

</script>
