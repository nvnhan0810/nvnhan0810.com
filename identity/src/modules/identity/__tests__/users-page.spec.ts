import { flushPromises, mount } from '@vue/test-utils'
import { expect, it, vi } from 'vitest'
import UsersPage from '../presentation/pages/UsersPage.vue'
vi.mock('../../../app/services', () => ({
  api: {
    users: vi.fn(async () => ({
      users: [
        {
          id: 'root-id',
          name: 'System Root',
          email: 'nguyenvannhan0810@gmail.com',
          avatar: null,
          enabled: true,
          is_system_root: true,
        },
        {
          id: 'user-id',
          name: 'Regular User',
          email: 'user@example.com',
          avatar: null,
          enabled: true,
          is_system_root: false,
        },
      ],
      next_cursor: null,
    })),
  },
}))
it('renders real user rows, protects root actions and filters loaded records', async () => {
  const wrapper = mount(UsersPage, { global: { stubs: { Modal: true } } })
  await flushPromises()
  expect(wrapper.text()).toContain('System Root')
  const rootLock = wrapper.find('button[aria-label="Khóa nguyenvannhan0810@gmail.com"]')
  expect(rootLock.attributes('disabled')).toBeDefined()
  expect(
    wrapper.find('button[aria-label="Khóa user@example.com"]').attributes('disabled'),
  ).toBeUndefined()
  await wrapper
    .find('input[aria-label="Tìm người dùng trong danh sách đã tải"]')
    .setValue('user@example.com')
  expect(wrapper.text()).toContain('Regular User')
  expect(wrapper.find('button[aria-label="Khóa nguyenvannhan0810@gmail.com"]').exists()).toBe(false)
  wrapper.unmount()
})
