module Tyche
  module Entities
    class Prize < ActiveRecord::Base
      self.table_name = 'prize'
      self.inheritance_column = 'unknown'
    end
  end
end
