<!--
  Matomo - free/libre analytics platform

  @link    https://matomo.org
  @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class="hiddenReportsManage">
    <ContentBlock :content-title="title">
      <p>{{ intro }}</p>
      <Alert severity="info" v-if="isGlobal">
        {{ translate('HiddenReports_GlobalSiteSpecificNote') }}
      </Alert>
      <Alert severity="info" v-else-if="hasGloballyHidden">
        {{ translate('HiddenReports_ScopeNote') }}
      </Alert>

      <div class="hiddenReportsSearch">
        <Field
          uicontrol="text"
          name="hiddenReportsSearch"
          v-model="search"
          :title="translate('HiddenReports_Search')"
          :full-width="true"
        />
      </div>

      <ActivityIndicator :loading="isLoading" />

      <p v-if="!isLoading && filteredCategories.length === 0" class="hiddenReportsEmpty">
        {{ translate('HiddenReports_NoReportsFound') }}
      </p>

      <div
        v-for="category in filteredCategories"
        :key="category.id"
        :class="['hiddenReportsCategory', { hiddenReportsCategoryOpen: isOpen(category) }]"
      >
        <div
          class="hiddenReportsCategoryHeader"
          role="button"
          tabindex="0"
          :aria-expanded="isOpen(category) ? 'true' : 'false'"
          @click="toggleOpen(category)"
          @keydown.enter.prevent="toggleOpen(category)"
          @keydown.space.prevent="toggleOpen(category)"
        >
          <span
            :class="['hiddenReportsChevron', chevronClass(category)]"
          />
          <span class="hiddenReportsCategoryName">{{ category.name }}</span>
          <span class="hiddenReportsCategoryCount">
            {{ translate('HiddenReports_HiddenOfTotal', `${hiddenCount(category)}`,
                         `${reportsOf(category).length}`) }}
          </span>
          <div
            class="switch hiddenReportsCategorySwitch"
            :title="translate('HiddenReports_HideCategory')"
            @click.stop
            @keydown.stop
          >
            <label>
              {{ translate('HiddenReports_Visible') }}
              <input
                type="checkbox"
                :checked="isCategoryHidden(category)"
                :disabled="!editableReportsOf(category).length || isCategorySaving(category)"
                @change="onToggleCategory(category, $event)"
              />
              <span class="lever" />
              {{ translate('HiddenReports_Hidden') }}
            </label>
          </div>
        </div>

        <table v-if="isOpen(category)" v-content-table class="entityTable hiddenReportsTable">
          <thead>
            <tr>
              <th class="hiddenReportsColName">{{ translate('HiddenReports_ColumnReport') }}</th>
              <th class="hiddenReportsColType">{{ translate('HiddenReports_ColumnType') }}</th>
              <th class="hiddenReportsColStatus">{{ translate('HiddenReports_ColumnStatus') }}</th>
            </tr>
          </thead>
          <tbody v-for="subcategory in category.subcategories" :key="subcategory.id">
            <tr class="hiddenReportsSubcategory">
              <td colspan="3">{{ subcategory.name }}</td>
            </tr>
            <tr
              v-for="report in subcategory.reports"
              :key="report.id"
              :class="{ hiddenReportsIsHidden: report.hidden }"
            >
              <td class="hiddenReportsColName">
                <span class="hiddenReportsName">{{ report.name }}</span>
                <code class="hiddenReportsId">{{ report.id }}</code>
              </td>
              <td class="hiddenReportsColType">
                <span :class="['hiddenReportsBadge', `hiddenReportsBadge-${report.type}`]">
                  {{ typeLabel(report.type) }}
                </span>
              </td>
              <td class="hiddenReportsColStatus">
                <div
                  class="switch"
                  :title="isLocked(report) ? translate('HiddenReports_HiddenGlobally') : ''"
                >
                  <label>
                    {{ translate('HiddenReports_Visible') }}
                    <input
                      type="checkbox"
                      :checked="report.hidden"
                      :disabled="isLocked(report) || !!saving[report.id]"
                      @change="onToggleReport(report, $event)"
                    />
                    <span class="lever" />
                    {{ translate('HiddenReports_Hidden') }}
                  </label>
                </div>
                <span v-if="isLocked(report)" class="hiddenReportsLocked icon-locked">
                  {{ translate('HiddenReports_HiddenGlobally') }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </ContentBlock>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import {
  ActivityIndicator,
  AjaxHelper,
  Alert,
  ContentBlock,
  ContentTable,
  NotificationsStore,
  translate,
} from 'CoreHome';
import { Field } from 'CorePluginsAdmin';

interface HiddenReport {
  id: string;
  name: string;
  type: 'core'|'customReport'|'customDimension'|'widget';
  hidden: boolean;
  hiddenGlobally: boolean;
}

interface ReportSubcategory {
  id: string;
  name: string;
  reports: HiddenReport[];
}

interface ReportCategory {
  id: string;
  name: string;
  subcategories: ReportSubcategory[];
}

interface ManageHiddenReportsState {
  categories: ReportCategory[];
  isLoading: boolean;
  search: string;
  saving: Record<string, boolean>;
  openCategories: Record<string, boolean>;
}

export default defineComponent({
  props: {
    scope: {
      type: String,
      required: true,
    },
    idSite: {
      type: Number,
      required: true,
    },
  },
  components: {
    ActivityIndicator,
    Alert,
    ContentBlock,
    Field,
  },
  directives: {
    ContentTable,
  },
  data(): ManageHiddenReportsState {
    return {
      categories: [],
      isLoading: true,
      search: '',
      saving: {},
      openCategories: {},
    };
  },
  created() {
    this.fetchReports();
  },
  computed: {
    isGlobal(): boolean {
      return this.scope === 'global';
    },
    isSearching(): boolean {
      return this.search.trim() !== '';
    },
    title(): string {
      return translate(this.isGlobal ? 'HiddenReports_GlobalTitle' : 'HiddenReports_SiteTitle');
    },
    intro(): string {
      return translate(this.isGlobal ? 'HiddenReports_GlobalIntro' : 'HiddenReports_SiteIntro');
    },
    hasGloballyHidden(): boolean {
      return this.categories.some(
        (category) => this.reportsOf(category).some((report) => report.hiddenGlobally),
      );
    },
    filteredCategories(): ReportCategory[] {
      const term = this.search.trim().toLowerCase();
      if (!term) {
        return this.categories;
      }

      const matches = (value: string) => value.toLowerCase().indexOf(term) !== -1;

      return this.categories.map((category) => {
        const categoryMatches = matches(category.name);

        const subcategories = category.subcategories.map((subcategory) => {
          if (categoryMatches || matches(subcategory.name)) {
            return subcategory;
          }

          return {
            ...subcategory,
            reports: subcategory.reports.filter(
              (report) => matches(report.name) || matches(report.id),
            ),
          };
        }).filter((subcategory) => subcategory.reports.length > 0);

        return { ...category, subcategories };
      }).filter((category) => category.subcategories.length > 0);
    },
  },
  methods: {
    fetchReports() {
      this.isLoading = true;

      AjaxHelper.fetch<ReportCategory[]>({
        method: this.isGlobal ? 'HiddenReports.getGlobalReports' : 'HiddenReports.getReports',
        idSite: this.idSite,
        filter_limit: '-1',
      }).then((categories) => {
        this.categories = categories;
      }).finally(() => {
        this.isLoading = false;
      });
    },
    isOpen(category: ReportCategory): boolean {
      return this.isSearching || !!this.openCategories[category.id];
    },
    chevronClass(category: ReportCategory): string {
      return this.isOpen(category) ? 'icon-chevron-down' : 'icon-chevron-right';
    },
    toggleOpen(category: ReportCategory) {
      this.openCategories[category.id] = !this.isOpen(category);
    },
    reportsOf(category: ReportCategory): HiddenReport[] {
      const reports: HiddenReport[] = [];
      category.subcategories.forEach((subcategory) => {
        reports.push(...subcategory.reports);
      });

      return reports;
    },
    editableReportsOf(category: ReportCategory): HiddenReport[] {
      return this.reportsOf(category).filter((report) => !this.isLocked(report));
    },
    hiddenCount(category: ReportCategory): number {
      return this.reportsOf(category).filter((report) => report.hidden).length;
    },
    isCategoryHidden(category: ReportCategory): boolean {
      const reports = this.reportsOf(category);
      return reports.length > 0 && reports.every((report) => report.hidden);
    },
    isCategorySaving(category: ReportCategory): boolean {
      return this.reportsOf(category).some((report) => !!this.saving[report.id]);
    },
    typeLabel(type: HiddenReport['type']): string {
      if (type === 'customReport') {
        return translate('HiddenReports_TypeCustomReport');
      }

      if (type === 'customDimension') {
        return translate('HiddenReports_TypeCustomDimension');
      }

      if (type === 'widget') {
        return translate('HiddenReports_TypeWidget');
      }

      return translate('HiddenReports_TypeCore');
    },
    isLocked(report: HiddenReport): boolean {
      return !this.isGlobal && report.hiddenGlobally;
    },
    onToggleReport(report: HiddenReport, event: Event) {
      const hidden = (event.target as HTMLInputElement).checked;
      const message = translate(
        hidden ? 'HiddenReports_SavedHidden' : 'HiddenReports_SavedVisible',
        report.name,
      );

      this.save([report], hidden, message);
    },
    onToggleCategory(category: ReportCategory, event: Event) {
      const hidden = (event.target as HTMLInputElement).checked;
      const reports = this.editableReportsOf(category).filter((report) => report.hidden !== hidden);
      const message = translate(
        hidden ? 'HiddenReports_SavedCategoryHidden' : 'HiddenReports_SavedCategoryVisible',
        category.name,
      );

      if (!reports.length) {
        return;
      }

      this.save(reports, hidden, message);
    },
    save(reports: HiddenReport[], hidden: boolean, message: string) {
      const previous = reports.map((report) => report.hidden);

      reports.forEach((report) => {
        report.hidden = hidden;
        this.saving[report.id] = true;
      });

      const params = this.isGlobal
        ? { method: 'HiddenReports.setReportsHiddenGlobally' }
        : { method: 'HiddenReports.setReportsHidden', idSite: this.idSite };

      AjaxHelper.post(params, {
        reportIds: reports.map((report) => report.id),
        hidden: hidden ? '1' : '0',
      }).then(() => {
        if (this.isGlobal) {
          reports.forEach((report) => {
            report.hiddenGlobally = hidden;
          });
        }

        NotificationsStore.show({
          id: 'HiddenReports.saved',
          message,
          context: 'success',
          type: 'toast',
        });
      }).catch(() => {
        reports.forEach((report, index) => {
          report.hidden = previous[index];
        });
      }).finally(() => {
        reports.forEach((report) => {
          delete this.saving[report.id];
        });
      });
    },
  },
});
</script>
