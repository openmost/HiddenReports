<!--
  Matomo - free/libre analytics platform

  @link    https://matomo.org
  @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class="hideReportsManage">
    <ContentBlock :content-title="title">
      <p>{{ intro }}</p>
      <Alert severity="info" v-if="isGlobal">
        {{ translate('HideReports_GlobalSiteSpecificNote') }}
      </Alert>
      <Alert severity="info" v-else-if="hasGloballyHidden">
        {{ translate('HideReports_ScopeNote') }}
      </Alert>

      <div class="hideReportsSearch">
        <Field
          uicontrol="text"
          name="hideReportsSearch"
          v-model="search"
          :title="translate('HideReports_Search')"
          :full-width="true"
        />
      </div>

      <ActivityIndicator :loading="isLoading" />

      <p v-if="!isLoading && filteredCategories.length === 0" class="hideReportsEmpty">
        {{ translate('HideReports_NoReportsFound') }}
      </p>

      <div
        v-for="category in filteredCategories"
        :key="category.id"
        :class="['hideReportsCategory', { hideReportsCategoryOpen: isOpen(category) }]"
      >
        <div
          class="hideReportsCategoryHeader"
          role="button"
          tabindex="0"
          :aria-expanded="isOpen(category) ? 'true' : 'false'"
          @click="toggleOpen(category)"
          @keydown.enter.prevent="toggleOpen(category)"
          @keydown.space.prevent="toggleOpen(category)"
        >
          <span
            :class="['hideReportsChevron', chevronClass(category)]"
          />
          <span class="hideReportsCategoryName">{{ category.name }}</span>
          <span class="hideReportsCategoryCount">
            {{ translate('HideReports_HiddenOfTotal', `${hiddenCount(category)}`,
                         `${reportsOf(category).length}`) }}
          </span>
          <div
            class="switch hideReportsCategorySwitch"
            :title="translate('HideReports_HideCategory')"
            @click.stop
            @keydown.stop
          >
            <label>
              {{ translate('HideReports_Visible') }}
              <input
                type="checkbox"
                :checked="isCategoryHidden(category)"
                :disabled="!editableReportsOf(category).length || isCategorySaving(category)"
                @change="onToggleCategory(category, $event)"
              />
              <span :class="['lever', { hideReportsLeverPartial: isCategoryPartial(category) }]" />
              {{ translate('HideReports_Hidden') }}
            </label>
          </div>
        </div>

        <table v-if="isOpen(category)" v-content-table class="entityTable hideReportsTable">
          <thead>
            <tr>
              <th class="hideReportsColName">{{ translate('HideReports_ColumnReport') }}</th>
              <th class="hideReportsColType">{{ translate('HideReports_ColumnType') }}</th>
              <th class="hideReportsColStatus">{{ translate('HideReports_ColumnStatus') }}</th>
            </tr>
          </thead>
          <tbody v-for="subcategory in category.subcategories" :key="subcategory.id">
            <tr class="hideReportsSubcategory">
              <td colspan="3">{{ subcategory.name }}</td>
            </tr>
            <tr
              v-for="report in subcategory.reports"
              :key="report.id"
              :class="{ hideReportsIsHidden: report.hidden }"
            >
              <td class="hideReportsColName">
                <span class="hideReportsName">{{ report.name }}</span>
                <code class="hideReportsId">{{ report.id }}</code>
              </td>
              <td class="hideReportsColType">
                <span :class="['hideReportsBadge', `hideReportsBadge-${report.type}`]">
                  {{ typeLabel(report.type) }}
                </span>
              </td>
              <td class="hideReportsColStatus">
                <div
                  class="switch"
                  :title="isLocked(report) ? translate('HideReports_HiddenGlobally') : ''"
                >
                  <label>
                    {{ translate('HideReports_Visible') }}
                    <input
                      type="checkbox"
                      :checked="report.hidden"
                      :disabled="isLocked(report) || !!saving[report.id]"
                      @change="onToggleReport(report, $event)"
                    />
                    <span class="lever" />
                    {{ translate('HideReports_Hidden') }}
                  </label>
                </div>
                <span v-if="isLocked(report)" class="hideReportsLocked icon-locked">
                  {{ translate('HideReports_HiddenGlobally') }}
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
      return translate(this.isGlobal ? 'HideReports_GlobalTitle' : 'HideReports_SiteTitle');
    },
    intro(): string {
      return translate(this.isGlobal ? 'HideReports_GlobalIntro' : 'HideReports_SiteIntro');
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
        method: this.isGlobal ? 'HideReports.getGlobalReports' : 'HideReports.getReports',
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
    isCategoryPartial(category: ReportCategory): boolean {
      const count = this.hiddenCount(category);
      return count > 0 && count < this.reportsOf(category).length;
    },
    isCategorySaving(category: ReportCategory): boolean {
      return this.reportsOf(category).some((report) => !!this.saving[report.id]);
    },
    typeLabel(type: HiddenReport['type']): string {
      if (type === 'customReport') {
        return translate('HideReports_TypeCustomReport');
      }

      if (type === 'customDimension') {
        return translate('HideReports_TypeCustomDimension');
      }

      if (type === 'widget') {
        return translate('HideReports_TypeWidget');
      }

      return translate('HideReports_TypeCore');
    },
    isLocked(report: HiddenReport): boolean {
      return !this.isGlobal && report.hiddenGlobally;
    },
    onToggleReport(report: HiddenReport, event: Event) {
      const hidden = (event.target as HTMLInputElement).checked;
      const message = translate(
        hidden ? 'HideReports_SavedHidden' : 'HideReports_SavedVisible',
        report.name,
      );

      this.save([report], hidden, message);
    },
    onToggleCategory(category: ReportCategory, event: Event) {
      const hidden = (event.target as HTMLInputElement).checked;
      const reports = this.editableReportsOf(category).filter((report) => report.hidden !== hidden);
      const message = translate(
        hidden ? 'HideReports_SavedCategoryHidden' : 'HideReports_SavedCategoryVisible',
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
        ? { method: 'HideReports.setReportsHiddenGlobally' }
        : { method: 'HideReports.setReportsHidden', idSite: this.idSite };

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
          id: 'HideReports.saved',
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
